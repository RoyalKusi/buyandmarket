<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * TDD §3.2 module 7 / §5.8: "validation, quality scoring, background
 * processing, compression/variants, alt-text" — deferred whole since
 * Run 1.2 rather than shipped half-built. "Quality scoring" here is a
 * deterministic resolution/aspect-ratio/size check, not a vision model's
 * judgement call (see docs/adr/0007 for why, and for alt-text's own
 * honest scope). Processing runs inline on the request rather than on a
 * queue — same reasoning Run 1.8's CHANGELOG gave for `ai:reindex`
 * running on-demand: this launch topology has no queue worker
 * guaranteed running (TDD §2.1), and an upload the seller is actively
 * waiting on is a poor fit for "eventually" anyway.
 */
class ProductImageService
{
    private const MIN_WIDTH = 500;

    private const MIN_HEIGHT = 500;

    private const MAX_ASPECT_RATIO = 3.0;

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    private const THUMB_DIMENSION = 300;

    private const LARGE_MAX_DIMENSION = 1600;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function upload(Product $product, UploadedFile $file, User $actor): ProductImage
    {
        $dimensions = getimagesize($file->getRealPath());

        if ($dimensions === false) {
            throw ValidationException::withMessages(['image' => 'That file could not be read as an image.']);
        }

        [$width, $height] = $dimensions;
        $diskPath = $file->store("products/{$product->id}", 'public');
        $rejectionReason = $this->qualityIssue($width, $height, $file->getSize());

        $image = $product->images()->create([
            'disk_path' => $diskPath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'width' => $width,
            'height' => $height,
            'size_bytes' => $file->getSize(),
            'is_primary' => $rejectionReason === null && $product->images()->where('status', 'processed')->doesntExist(),
            'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
            'status' => $rejectionReason === null ? 'processed' : 'rejected',
            'rejection_reason' => $rejectionReason,
        ]);

        if ($rejectionReason === null) {
            $this->generateVariants($image);
        }

        $this->auditLogger->log(
            actor: $actor,
            action: $rejectionReason === null ? 'product.image.uploaded' : 'product.image.rejected',
            subject: $product,
            before: null,
            after: ['image_id' => $image->id, 'rejection_reason' => $rejectionReason],
        );

        return $image;
    }

    public function destroy(ProductImage $image, User $actor): void
    {
        $disk = Storage::disk('public');
        $wasPrimary = $image->is_primary;
        $product = $image->product;

        foreach (['thumb', 'large'] as $variant) {
            $disk->delete($image->variantPath($variant));
        }
        $disk->delete($image->disk_path);
        $image->delete();

        if ($wasPrimary) {
            $next = $product->images()->where('status', 'processed')->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        $this->auditLogger->log(actor: $actor, action: 'product.image.deleted', subject: $product, before: ['image_id' => $image->id], after: null);
    }

    public function makePrimary(ProductImage $image): void
    {
        $image->product->images()->where('status', 'processed')->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    /**
     * @param  array<int, int>  $orderedImageIds
     */
    public function reorder(Product $product, array $orderedImageIds): void
    {
        foreach ($orderedImageIds as $position => $imageId) {
            $product->images()->whereKey($imageId)->update(['sort_order' => $position]);
        }
    }

    private function qualityIssue(int $width, int $height, int $sizeBytes): ?string
    {
        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            return sprintf(
                'Image is %dx%dpx — the minimum is %dx%dpx.',
                $width, $height, self::MIN_WIDTH, self::MIN_HEIGHT,
            );
        }

        $longest = max($width, $height);
        $shortest = max(1, min($width, $height));

        if (($longest / $shortest) > self::MAX_ASPECT_RATIO) {
            return 'Image is too narrow/wide for a product photo — crop it closer to square.';
        }

        if ($sizeBytes > self::MAX_SIZE_BYTES) {
            return 'Image file is larger than the 10MB limit.';
        }

        return null;
    }

    private function generateVariants(ProductImage $image): void
    {
        $disk = Storage::disk('public');
        $source = $this->decode($disk->path($image->disk_path), $image->mime_type);

        if ($source === null) {
            return;
        }

        $this->writeCropped($source, self::THUMB_DIMENSION, self::THUMB_DIMENSION, $disk->path($image->variantPath('thumb')), $image->mime_type);
        $this->writeScaled($source, self::LARGE_MAX_DIMENSION, $disk->path($image->variantPath('large')), $image->mime_type);

        imagedestroy($source);
    }

    private function decode(string $path, string $mimeType): ?GdImage
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path) ?: null,
            'image/png' => imagecreatefrompng($path) ?: null,
            'image/webp' => imagecreatefromwebp($path) ?: null,
            default => null,
        };
    }

    /**
     * Centre-cropped to exactly $targetWidth x $targetHeight — the
     * thumbnail grid needs a uniform shape regardless of the source's
     * own aspect ratio.
     */
    private function writeCropped(GdImage $source, int $targetWidth, int $targetHeight, string $destinationPath, string $mimeType): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $cropWidth = (int) round($targetWidth / $scale);
        $cropHeight = (int) round($targetHeight / $scale);
        $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);
        $this->preserveTransparency($destination, $mimeType);

        imagecopyresampled($destination, $source, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        $this->encode($destination, $destinationPath, $mimeType);
        imagedestroy($destination);
    }

    /**
     * Scaled down to fit within $maxDimension on its longest side,
     * preserving aspect ratio — never upscaled.
     */
    private function writeScaled(GdImage $source, int $maxDimension, string $destinationPath, string $mimeType): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1.0, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $destination = imagecreatetruecolor($targetWidth, $targetHeight);
        $this->preserveTransparency($destination, $mimeType);

        imagecopyresampled($destination, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        $this->encode($destination, $destinationPath, $mimeType);
        imagedestroy($destination);
    }

    private function preserveTransparency(GdImage $image, string $mimeType): void
    {
        if ($mimeType === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
    }

    private function encode(GdImage $image, string $destinationPath, string $mimeType): void
    {
        match ($mimeType) {
            'image/png' => imagepng($image, $destinationPath),
            'image/webp' => imagewebp($image, $destinationPath),
            default => imagejpeg($image, $destinationPath, 85),
        };
    }
}
