<?php

namespace Paragraph\Storage;

class ViewStorage {
    const FILENAME_PREFIX = 'paragraph_view_snapshot';

    public function has($name)
    {
        return file_exists($this->filename($name));
    }

    public function save($name, $contents)
    {
        file_put_contents($this->filename($name), $contents);
    }

    public function all()
    {
        $matches = array_filter(scandir(storage_path()), function($filename) {
            return strpos($filename, $this::FILENAME_PREFIX) === 0;
        });

        return array_map(fn($f) => storage_path($f), $matches);
    }

    protected function filename($key)
    {
        return storage_path($this::FILENAME_PREFIX . "_{$key}.html");
    }
}
