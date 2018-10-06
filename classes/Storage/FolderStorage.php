<?php
namespace Grav\Plugin\FlexDirectory\Storage;

use Grav\Common\Grav;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Filesystem\Folder;
use RuntimeException;

/**
 * Class FolderStorage
 * @package Grav\Plugin\FlexDirectory\Storage
 */
class FolderStorage
{
    /**
     * @var array
     */
    protected $entries;

    /**
     * @var string
     */
    protected $filename = 'default';

    /**
     * @var string
     */
    protected $path = '';

    /**
     * @var string
     */
    protected $format = 'json';

    /**
     * @var array|FolderStorage[]
     */
    static protected $instances = [];

    /**
     * Get file instance.
     *
     * @param  string  $filename
     * @return static
     */
    public static function instance($path = '', $format = 'json')
    {
        if (!is_string($path) && $path) {
            throw new \InvalidArgumentException('Path should be non-empty string');
        }
        if (!is_string($format) && $format) {
            throw new \InvalidArgumentException('Format should be non-empty string');
        }
        if (!isset(static::$instances[$path])) {
            static::$instances[$path] = new static;
            static::$instances[$path]->init($path, $format);
        }
        return static::$instances[$path];
    }

    /**
     * Set path and type.
     *
     * @param $path
     * @param $type
     */
    protected function init($path = '', $format = 'json')
    {
        $this->grav = Grav::instance();
        $parts = explode('%s', $path);
        $this->path = rtrim($parts[0], '/');
        if (!empty($parts[1])) {
            $this->filename = '%s/'.basename(ltrim($parts[1], '/'), '.'.$format);
        }

        $lang = pathinfo($this->filename, PATHINFO_EXTENSION);
        if ($lang && (empty($this->grav['session']->admin_lang) || $lang !== $this->grav['session']->admin_lang)) {
            $this->filename = rtrim($this->filename, '.'.$lang) . '.'.$this->grav['session']->admin_lang;
        }

        $this->format = $format;
    }

    /**
     * Prevent constructor from being used.
     */
    protected function __construct()
    {
    }

    /**
     * Prevent cloning.
     */
    protected function __clone()
    {
        //Me not like clones! Me smash clones!
    }

    /**
     * Free the file instance.
     */
    public function free()
    {
        unset(static::$instances[$this->path]);
    }

    /**
     * Get a file.
     */
    protected function getFile($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'json':
                $file = CompiledJsonFile::instance($filename);
                break;
            case 'yaml':
                $file = CompiledYamlFile::instance($filename);
                break;
            case 'md':
                $file = CompiledMarkdownFile::instance($filename);
                break;
            default:
                throw new RuntimeException('Unknown extension type ' . $extension);
        }
        return $file;
    }

    /**
     * Read a file.
     */
    protected function readfile($filename)
    {
        $file = $this->getFile($filename);
        $data = (array)$file->content();
        if (get_class($file) === 'Grav\Common\File\CompiledMarkdownFile') {
            $data['header']['markdown'] = $data['markdown'];
            $data = $data['header'];
        }
        return $data;
    }

    /**
     * (Re)Load a folder.
     */
    public function load()
    {
        $params = [
            'pattern' => '|\.'.$this->format.'|',
            'folders' => false,
            'levels'  => 2
        ];
        $all = Folder::all($this->path, $params);
        foreach ($all as $name) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $lang = pathinfo( rtrim(basename($name), '.'.$extension), PATHINFO_EXTENSION);
            if ($extension !== $this->format) {
                continue;
            }
            if ($lang && (empty($this->grav['session']->admin_lang) || $lang !== $this->grav['session']->admin_lang)) {
                continue;
            }
            if ( !isset($this->entries[dirname($name)])) {
                $this->entries[dirname($name)] = $this->readfile($this->path.'/'.$name);
            }
        }
        return $this->entries;
    }

    /**
     * Save to folder.
     *
     * @param  mixed  $data  Data to be saved, usually array.
     * @throws \RuntimeException
     */
    public function save($data = null)
    {
        try {
            foreach ($data as $key=>$entry) {
                $file = $this->getFile( sprintf('%s/'.$this->filename.'.%s', $this->path, $key, $this->format) );
                if (get_class($file) === 'Grav\Common\File\CompiledMarkdownFile') {
                    $markdown = $entry['markdown'];
                    unset($entry['markdown']);
                    $entry = ['header' => $entry, 'markdown' => $markdown];
                }
                $file->save($entry);
                $file->free();
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Failed to save %s: %s', $this->path, $e->getMessage()), 500, $e);
        }
    }

    /**
     * Detele in folder.
     *
     * @param  mixed  $key  Key to be delete.
     * @throws \RuntimeException
     */
    public function delete($key)
    {
        $filename = sprintf('%s/'.$this->filename.'.%s', $this->path, $key, $this->format);
        // print_r($filename);die();
        try {
            Folder::delete( dirname($filename) );
            @unlink($filename);

        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Failed to delete %s: %s', $this->path, $e->getMessage()), 500, $e);
        }
    }

    /**
     * Get/set parsed file contents.
     *
     * @param mixed $var
     * @return string|array
     * @throws \RuntimeException
     */
    public function content($var = null)
    {
        if ($var !== null) {
            $this->entries = $var;
        } elseif ($this->entries === null) {
            try {
                $this->load();
            } catch (\Exception $e) {
                throw new \RuntimeException(sprintf('Failed to read %s: %s', $this->path, $e->getMessage()), 500, $e);
            }
        }
        return $this->entries;
    }

    /**
     * Get/set path.
     *
     * @param string $var
     * @return string
     */
    public function filename($var = null)
    {
        if ($var !== null) {
            $this->path = $var;
        }
        return $this->path;
    }

}
