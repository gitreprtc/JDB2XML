<?php
// Copyright Robin Colbers.
defined('_JEXEC') or die;

/**
 * Simple data container describing what preview found and what import will do.
 * Stored inside the preview userstate so import executes the same plan.
 *
 * Legacy-friendly (Joomla 4/5 compatible) without relying on autoloading.
 */
class Jdb2xmlImportPlan
{
    public string $fileKey;
    /** @var array<int,array> */
    public array $categories = [];
    /** @var array<int,array> */
    public array $tags = [];
    /** @var array<int,string> */
    public array $warnings = [];

    public function __construct(string $fileKey)
    {
        $this->fileKey = $fileKey;
    }

    public function addCategory(array $row): void
    {
        $this->categories[] = $row;
    }

    public function addTag(array $row): void
    {
        $this->tags[] = $row;
    }

    public function addWarning(string $msg): void
    {
        $this->warnings[] = $msg;
    }

    public function hasBlocking(): bool
    {
        // Blocking = any record with action 'skipped' and exclude=false
        foreach (array_merge($this->categories, $this->tags) as $r) {
            if (($r['action'] ?? '') === 'skipped' && empty($r['exclude'])) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array
    {
        return [
            'fileKey' => $this->fileKey,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'warnings' => $this->warnings,
        ];
    }

    public static function fromArray(array $a): self
    {
        $p = new self((string)($a['fileKey'] ?? ''));
        $p->categories = is_array($a['categories'] ?? null) ? $a['categories'] : [];
        $p->tags = is_array($a['tags'] ?? null) ? $a['tags'] : [];
        $p->warnings = is_array($a['warnings'] ?? null) ? $a['warnings'] : [];
        return $p;
    }
}
