<?php
namespace Grav\Plugin\FlexDirectory;

use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;

/**
 * Class FlexDirectory
 * @package Grav\Plugin\FlexDirectory\Entities
 */
class FlexDirectory implements \Countable
{
    /**
     * @var array|FlexType[]
     */
    protected $types = [];

    public function __construct(array $types = [])
    {
        foreach ($types as $type => $config) {
            $this->types[$type] = new FlexType($type, $config, true);
        }
    }

    /**
     * @return array|FlexType[]
     */
    public function getAll()
    {
        $params = [
            'pattern' => '|\.yaml|',
            'value' => 'Url',
            'recursive' => false
        ];

        $directories = $this->getDirectories();
        $all = Folder::all('blueprints://flex-directory', $params);

        foreach ($all as $url) {
            $type = basename($url, '.yaml');
            if (!isset($directories[$type])) {
                $directories[$type] = new FlexType($type, $url);
            }
        }

        ksort($directories);

        return $directories;
    }

    /**
     * @return array|FlexType[]
     */
    public function getDirectories()
    {
        return $this->types;
    }

    /**
     * @param string|null $type
     * @return FlexType|null
     */
    public function getDirectory($type = null)
    {
        if (!$type) {
            return reset($this->types) ?: null;
        }

        return isset($this->types[$type]) ? $this->types[$type] : null;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->types);
    }

    /**
     * @param string
     * @return array
     */
    static function getAllFromFolder($path)
    {
        $locator = Grav::instance()['locator'];

        $params = [
            'pattern'   => '|\.yaml|',
            'folders'   => false,
            'recursive' => false
        ];

        $all = Folder::all($locator->findResource($path, false, true), $params);

        $directories = [];
        foreach ($all as $file) {
            $directories[rtrim($file, '.yaml')] = $path . '/' . $file;
        }

        ksort($directories);

        return $directories;
    }

}
