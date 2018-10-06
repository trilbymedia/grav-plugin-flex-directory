<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Helpers\Base32;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use Grav\Plugin\FlexDirectory\Storage\FolderStorage;

/**
 * Class FlexType
 * @package Grav\Plugin\FlexDirectory\Entities
 */
class FlexType
{
    /** @var string */
    protected $type;
    /** @var string */
    protected $blueprint_file;
    /** @var Blueprint */
    protected $blueprint;
    /** @var FlexCollection */
    protected $collection;
    /** @var bool */
    protected $enabled;
    /** @var array */
    protected $changes;

    /**
     * FlexType constructor.
     * @param string $type
     * @param string $blueprint_file
     * @param bool $enabled
     */
    public function __construct($type, $blueprint_file, $enabled = false)
    {
        $this->type = $type;
        $this->blueprint_file = $blueprint_file;
        $this->enabled = (bool) $enabled;
        $this->changes = [];
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->getBlueprint()->get('title', ucfirst($this->getType()));
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getBlueprint()->get('description', '');
    }

    /**
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function getConfig($name = null, $default = null)
    {
        $path = 'config' . ($name ? '/' . $name : '');

        return $this->getBlueprint()->get($path, $default);
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint()
    {
        if (null === $this->blueprint) {
            $this->blueprint = (new Blueprint($this->blueprint_file))->load();
            if ($this->blueprint->get('type') === 'flex-directory') {
                $blueprintBase = (new Blueprint('plugin://flex-directory/blueprints/flex-directory.yaml'))->load();
                $this->blueprint->extend($blueprintBase, true);
            }
            $this->blueprint->init();
            if (empty($this->blueprint->fields())) {
                throw new RuntimeException(sprintf('Blueprint for %s is missing', $this->type));
            }
        }

        return $this->blueprint;
    }

    public function getBlueprintFile()
    {
        return $this->blueprint_file;
    }

    /**
     * @return FlexCollection
     */
    public function getCollection()
    {
        return $this->load();
    }

    /**
     * @return FlexCollection
     */
    public function load()
    {
        if (null === $this->collection) {
            $raw = (array)$this->getFile()->content();
            $entries = [];
            foreach ($raw as $key => $entry) {
                $entries[$key] = $this->createObject($entry, $key);
            }

            $this->collection = $this->createCollection($entries);
        }

        return $this->collection;
    }

    /**
     * @return bool
     */
    public function save()
    {
        $file = $this->getFile();
        $type = $this->getStorageType();

        switch ($type) {
            case 'folder':
                foreach ($this->changes as $key => $action) {
                    switch ($action) {
                        case 'update':
                            $file->save([ $key => $this->getCollection()->get($key)->jsonSerialize() ]);
                            break;
                        case 'delete':
                            $file->delete($key);
                            break;
                        default:
                            throw new RuntimeException('Unknown save action');
                    }
                }
                break;
            case 'file':
                $file->save($this->collection->jsonSerialize());
                break;
            default:
                throw new RuntimeException('Unknown storage type');
        }

        $file->free();

        return true;
    }

    /**
     * @param array $data
     * @return FlexObject
     */
    public function create(array $data)
    {
        return $this->update($data);
    }

    /**
     * @param array $data
     * @param string|null $key
     * @return FlexObject
     */
    public function update(array $data, $key = null)
    {
        /** @var FlexObject $object */
        $object = null !== $key ? $this->getCollection()->get($key) : null;
        $field_key = $this->getStorageKey();

        if (null === $object) {
            if (null !== $field_key && !empty($data[$field_key])) {
                $key = $data[$field_key];
            }
            if (empty($key) || $key == '') {
                $key = $this->getNextKey();

            }
            $object = $this->createObject($data, $key);
        } else {
            if (null !== $field_key && !empty($data[$field_key])) {
                if (strcmp($key, $data[$field_key]) != 0) {
                    $this->changes[$key] = 'delete';
                    $key = $data[$field_key];
                }
            }
            $blueprint = $this->getBlueprint();

            $object = $this->createObject($blueprint->mergeData($object->jsonSerialize(), $data), $key);
        }

        $this->changes[$key] = 'update';

        $this->getCollection()->set($key, $object);

        return $object;
    }

    /**
     * @param string $key
     * @return FlexObject
     */
    public function remove($key)
    {
        $this->changes[$key] = 'delete';

        return $this->getCollection()->remove($key);
    }

    /**
     * @return string
     */
    protected function getNextKey()
    {
        $collection = $this->getCollection();

        do {
            $key = strtolower(Base32::encode(Utils::generateRandomString(10)));
        } while ($collection->containsKey($key));

        return $key;
    }

    /**
     * @param bool $resolve
     * @return string
     */
    public function getStorageFilename($resolve = false)
    {
        $filename = $this->getConfig('data/storage/file', 'user://data/flex-directory/' . $this->getType() . '.json');

        if ($resolve) {
            $grav = Grav::instance();
            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            $filename = $locator->findResource($filename, false, true);
        }

        return $filename;
    }

    /**
     * @return string
     */
    public function getStorageType()
    {
        return $this->getConfig('data/storage/type', 'file');
    }

    /**
     * @return string
     */
    public function getStorageKey()
    {
        return $this->getConfig('data/storage/key');
    }

    /**
     * @return string
     */
    public function getStorage()
    {
        return $this->getFile()->filename();
    }

    /**
     * @return CompiledJsonFile|CompiledYamlFile|CompiledMarkdownFile
     * @throws RuntimeException
     */
    protected function getFile()
    {
        $filename = $this->getStorageFilename(true);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $type = $this->getStorageType();

        switch ($type) {
            case 'folder':
                $file = FolderStorage::instance($filename, $extension);
                break;
            case 'file':
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
                break;
            default:
                throw new RuntimeException('Unknown storage type');
        }

        return $file;
    }

    /**
     * @param array $data
     * @param $key
     * @return FlexObject
     */
    protected function createObject(array $data, $key)
    {
        $className = $this->getConfig('data/object', 'Grav\\Plugin\\FlexDirectory\\FlexObject');

        return new $className($data, $key, $this);
    }

    /**
     * @param array $entries
     * @return FlexCollection
     */
    protected function createCollection(array $entries)
    {
        $className = $this->getConfig('data/collection', 'Grav\\Plugin\\FlexDirectory\\FlexCollection');

        return new $className($entries, $this);
    }
}
