<?php

namespace App\Contracts\Ai;

/**
 * TDD §5.4/§8.4: a provider-agnostic chat-completion boundary — the
 * tool-calling/retrieval logic above this line never depends on any
 * vendor's wire format. Modelled loosely on the OpenAI-compatible chat
 * completions shape (the most widely mirrored one), since the TDD names
 * no specific vendor.
 */
interface LlmProvider
{
    /**
     * @param  list<array{role: string, content: ?string, name?: string, tool_call_id?: string}>  $messages
     * @param  list<array{name: string, description: string, parameters: array}>  $tools
     */
    public function complete(array $messages, array $tools = []): LlmCompletion;
}
