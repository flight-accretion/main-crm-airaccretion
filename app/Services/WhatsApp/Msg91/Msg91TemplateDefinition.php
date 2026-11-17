<?php

namespace App\Services\WhatsApp\Msg91;

class Msg91TemplateDefinition
{
    public function variants(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \RuntimeException('MSG91 template list contains an invalid row.');
            $variants = $row['languages'] ?? [$row];
            if (!is_array($variants) || !array_is_list($variants) || !$variants) throw new \RuntimeException('MSG91 language variants are incomplete.');
            foreach ($variants as $variant) {
                if (!is_array($variant)) throw new \RuntimeException('MSG91 language variant is invalid.');
                $components = $variant['code'] ?? $variant['components'] ?? $row['components'] ?? null;
                if (is_string($components)) $components = json_decode($components, true);
                $name = $row['template_name'] ?? $row['name'] ?? null;
                $language = $variant['language'] ?? $row['language'] ?? null;
                $result[] = [
                    'name' => is_string($name) ? trim($name) : '',
                    'language' => is_string($language) ? trim($language) : '',
                    'status' => strtolower(trim((string) ($variant['status'] ?? $row['status'] ?? 'unknown'))),
                    'provider_id' => $variant['msg91_template_id'] ?? $variant['template_id'] ?? $variant['id'] ?? $row['template_id'] ?? $row['id'] ?? null,
                    'category' => strtoupper(trim((string) ($variant['category'] ?? $row['category'] ?? ''))),
                    'components' => $components,
                    'variables' => $variant['variables'] ?? [],
                ];
            }
        }
        return $result;
    }

    public function fingerprint(?string $category, $components): ?string
    {
        if (!$category || !is_array($components) || !array_is_list($components) || !$components) return null;
        $body = collect($components)->first(fn ($item) => is_array($item) && strtoupper($item['type'] ?? '') === 'BODY');
        if (!is_string($body['text'] ?? null) || trim($body['text']) === '') return null;
        $normalize = function ($value) use (&$normalize) {
            if (!is_array($value)) return $value;
            unset($value['example']);
            if (isset($value['type'])) $value['type'] = strtoupper($value['type']);
            if (!array_is_list($value)) ksort($value);
            return array_map($normalize, $value);
        };
        return hash('sha256', json_encode([strtoupper($category), $normalize($components)], JSON_THROW_ON_ERROR));
    }
}
