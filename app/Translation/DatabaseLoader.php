<?php

namespace App\Translation;

use App\Models\Translation;
use Illuminate\Contracts\Translation\Loader as LoaderContract;

class DatabaseLoader implements LoaderContract
{
    protected $fileLoader;

    public function __construct($fileLoader)
    {
        $this->fileLoader = $fileLoader;
    }

    public function load($locale, $group, $namespace = null)
    {
        // First get file translations
        $files = $this->fileLoader->load($locale, $group, $namespace);

        // Overrides are for the app's own groups; vendor namespaces keep theirs.
        if ($namespace !== null && $namespace !== '*') {
            return $files;
        }

        // Then DB translations for locale/group, which win over the files. A
        // database that is not reachable or not migrated yet (first boot, a
        // migration run) must leave the file translations working.
        try {
            $rows = Translation::where('locale', $locale)->where('group', $group)->get(['key', 'value']);
        } catch (\Throwable $e) {
            return $files;
        }

        $db = [];
        foreach ($rows as $r) {
            data_set($db, $r->key, $r->value);
        }

        return array_replace_recursive($files, $db);
    }

    public function addNamespace($namespace, $hint)
    {
        if (method_exists($this->fileLoader, 'addNamespace')) {
            $this->fileLoader->addNamespace($namespace, $hint);
        }
    }

    public function namespaces()
    {
        if (method_exists($this->fileLoader, 'namespaces')) {
            return $this->fileLoader->namespaces();
        }
        return [];
    }

    public function addJsonPath($path)
    {
        if (method_exists($this->fileLoader, 'addJsonPath')) {
            $this->fileLoader->addJsonPath($path);
        }
    }

    public function jsonPaths()
    {
        if (method_exists($this->fileLoader, 'jsonPaths')) {
            return $this->fileLoader->jsonPaths();
        }
        return [];
    }
}
