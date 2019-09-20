<?php
namespace Grav\Plugin\FlexDirectory\Storage;

use Grav\Common\Grav;
use Grav\Common\Data\BlueprintSchema;
use Grav\Common\Page\Media;
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
     * @var array
     */
    protected $blueprint = NULL;

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
    public static function instance($path = '', $format = 'json', $blueprint = NULL)
    {
        if (!is_string($path) && $path) {
            throw new \InvalidArgumentException('Path should be non-empty string');
        }
        if (!is_string($format) && $format) {
            throw new \InvalidArgumentException('Format should be non-empty string');
        }
        if (!isset(static::$instances[$path])) {
            static::$instances[$path] = new static;
            static::$instances[$path]->init($path, $format, $blueprint);
        }
        return static::$instances[$path];
    }

    /**
     * Set path and type.
     *
     * @param $path
     * @param $type
     */
    protected function init($path = '', $format = 'json', $blueprint = NULL)
    {
        $this->grav = Grav::instance();
        $parts = explode('%s', $path);
        $this->path = rtrim($parts[0], '/');
        if (!empty($parts[1])) {
            if ($parts[1] === '%s.'.$format) {
                $this->filename = '%s.'.$format;
            } else {
                $this->filename = '%s/'.basename(ltrim($parts[1], '/'), '.'.$format);
            }
        }

        $lang = pathinfo($this->filename, PATHINFO_EXTENSION);
        if ($lang && (empty($this->grav['session']->admin_lang) || $lang !== $this->grav['session']->admin_lang)) {
            $this->filename = rtrim($this->filename, '.'.$lang) . '.'.$this->grav['session']->admin_lang;
        }

        $this->format = $format;

        $this->blueprint = $blueprint ? $blueprint : new BlueprintSchema();
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
     * Get a file.
     *
     * @param Page $page
     * @return CompiledYamlFile
     */
    protected function getFileFrontmatter($filename)
    {
        $file = CompiledYamlFile::instance(dirname($filename) . '/frontmatter.yaml');
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
        $data['media'] = new Media(dirname($filename));
        // If there's a `frontmatter.yaml` file merge that in with the page header
        // note page's own frontmatter has precedence and will overwrite any values from page file
        $frontmatterFile = $this->getFileFrontmatter($filename);
        if ($frontmatterFile->exists()) {
            $frontmatter_data = (array)$frontmatterFile->content();
            $data = array_replace_recursive($frontmatter_data, $data);
            $frontmatterFile->free();
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
        if (empty($this->grav['session']->admin_lang)) {
            $site_lang = $this->grav['language']->getLanguage();
        } else {
            $site_lang = $this->grav['session']->admin_lang;
        }
        $all = Folder::all($this->path, $params);
        foreach ($all as $name) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $lang = pathinfo( pathinfo(basename($name), PATHINFO_FILENAME), PATHINFO_EXTENSION);
            if ($extension !== $this->format) {
                continue;
            }
            if ($lang && (empty($site_lang) || $lang !== $site_lang)) {
                continue;
            }
            $parts = explode('/',$name);
            $key = empty($parts[1]) ? pathinfo($name, PATHINFO_FILENAME) : dirname($name);
            if ( !isset($this->entries[$key])) {
                $this->entries[$key] = $this->readfile($this->path.'/'.$name);
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
        $frontmatter_blocks = $this->grav['config']->get('plugins.admin-frontmatter-yaml.blocks', '');
        if (!is_array($frontmatter_blocks)) {
            $frontmatter_blocks = explode(',', $frontmatter_blocks);
        }
        $blocks =  array_unique(array_merge(
            array_map(
                function($field){ if (!empty($field['multilingual']) && $field['multilingual']) return $field['name']; },
                array_values(array_filter($this->blueprint->schema()->getState()['items'], function($field) { return !empty($field['multilingual']); }))
            ),
            $frontmatter_blocks,
            ['markdown']
        ));
        $languages = $this->grav['language']->getLanguages();
        try {
            foreach ($data as $key=>$entry) {
                unset($entry['media']);
                $filename = sprintf('%s/'.$this->filename.'.%s', $this->path, $key, $this->format);
                //
                $frontmatter = [];
                $header = [];
                $header_all = $entry;
                foreach ($header_all as $block_name=>$block_data) {
                    if (in_array($block_name, $blocks)) {
                        $header[$block_name] = $header_all[$block_name];
                    } else {
                        $frontmatter[$block_name] = $header_all[$block_name];
                    }
                }
                $frontmatterFile = $this->getFileFrontmatter($filename);
                $frontmatterFile->save($frontmatter);
                $frontmatterFile->free();
                $entry = $header;
                //
                $file = $this->getFile($filename);
                if (get_class($file) === 'Grav\Common\File\CompiledMarkdownFile') {
                    $markdown = $entry['markdown'];
                    unset($entry['markdown']);
                    $entry = ['header' => $entry, 'markdown' => $markdown];
                }
                $file->save($entry);
                $file->free();
                [ 'dirname' => $dirname, 'filename' => $name_width_lang] = pathinfo($filename);
                [ 'filename' => $shortname, 'extension' => $ext ] = pathinfo($name_width_lang);
                foreach ($languages as $lang) {
                	$othername = $dirname.'/'.$shortname.'.'.$lang.'.'.$this->format;
                	if ($lang === $ext || file_exists($othername)) continue;
                	touch($othername);
				}
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
