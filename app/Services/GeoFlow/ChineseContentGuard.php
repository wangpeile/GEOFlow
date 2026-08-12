<?php

namespace App\Services\GeoFlow;

use Illuminate\Validation\ValidationException;

final class ChineseContentGuard
{
    /**
     * @param  array<string, string>  $fields
     */
    public function validate(array $fields): void
    {
        foreach ($fields as $field => $content) {
            $this->validateField($field, $content);
        }
    }

    public function validateField(string $field, string $content): void
    {
        $plain = trim(strip_tags($content));
        if ($plain === '') {
            throw ValidationException::withMessages([$field => '内容不能为空。']);
        }

        $instructionPatterns = [
            '/\bas an ai\b/i',
            '/\bhere (?:is|are)\b/i',
            '/\bplease note\b/i',
            '/\bi cannot\b/i',
            '/\bsystem prompt\b/i',
            '/\buser prompt\b/i',
            '/\bthe user (?:wants|asked|is asking)\b/i',
            '/\blet me (?:identify|analy[sz]e|think|write|craft)\b/i',
            '/\b(?:we|i) need to\b/i',
            '/\bthis is an? h[1-6] section\b/i',
        ];
        foreach ($instructionPatterns as $pattern) {
            if (preg_match($pattern, $plain) === 1) {
                throw ValidationException::withMessages([$field => '内容包含无意义的英文模型说明，请重写。']);
            }
        }

        $withoutUrls = preg_replace('#https?://\S+#iu', '', $plain) ?? $plain;
        if (preg_match('/(?:\b[A-Za-z][A-Za-z\x{2019}\x{0027}-]*\b[\s,;:()\-]*){8,}/u', $withoutUrls) === 1) {
            throw ValidationException::withMessages([$field => '内容包含连续的英文说明，请改为简体中文。']);
        }

        $hanCount = preg_match_all('/\p{Han}/u', $plain);
        $letterCount = preg_match_all('/[\p{L}\p{N}]/u', $plain);
        if ($letterCount > 20 && $hanCount / max(1, $letterCount) < 0.35) {
            throw ValidationException::withMessages([$field => '内容不是以简体中文为主，请重写。']);
        }

        if (preg_match('/[體為與這個們說還後發現實際麼於從將應讓]/u', $plain) === 1) {
            throw ValidationException::withMessages([$field => '内容疑似包含繁体中文，请转换为简体中文。']);
        }
    }
}
