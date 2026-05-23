<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'conversation_id' => Conversation::factory(),
            'file_path'       => 'chat-attachments/test.pdf',
            'original_name'   => 'test.pdf',
            'mime_type'       => 'application/pdf',
            'file_size'       => 1024,
            'status'          => 'processed',
        ];
    }
}
