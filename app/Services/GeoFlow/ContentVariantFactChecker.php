<?php

namespace App\Services\GeoFlow;

final class ContentVariantFactChecker
{
    /** @return array{passed: bool, extra_hard_facts: array<int, string>, checked_at: string} */
    public function inspect(string $source, string $generated): array
    {
        $sourceFacts = $this->hardFacts($source);
        $generatedFacts = $this->hardFacts($generated);
        $extra = array_values(array_diff($generatedFacts, $sourceFacts));

        return [
            'passed' => $extra === [],
            'extra_hard_facts' => array_slice($extra, 0, 20),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<int, string> */
    private function hardFacts(string $content): array
    {
        preg_match_all('/https?:\/\/[^\s<>"\']+|\d{4}年\d{1,2}月\d{1,2}日|\d+(?:\.\d+)?%|\d+(?:\.\d+)?/u', strip_tags($content), $matches);

        return array_values(array_unique(array_map(
            static fn (string $fact): string => mb_strtolower(rtrim($fact, '.,，。；;:：')),
            $matches[0] ?? [],
        )));
    }
}
