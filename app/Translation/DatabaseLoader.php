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

        // Then get DB translations for locale/group and merge (DB overrides file)
        $rows = Translation::where('locale', $locale)->where('group', $group)->get();
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
