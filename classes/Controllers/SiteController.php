<?php
namespace Grav\Plugin\FlexDirectory\Controllers;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Common\Utils;

/**
 * Class SiteController
 * @package Grav\Plugin\FlexDirectory
 */
class SiteController
{
	protected $active;

	/**
	 * @param Plugin   $plugin
	 */
	public function __construct(Plugin $plugin)
	{
			$this->grav = Grav::instance();
			$this->active = false;
	}

	/**
	 * Performs an action.
	 * @throws \RuntimeException
	 */
	public function execute()
	{
			$success = false;

			return $success;
	}

	public function isActive()
	{
			return (bool) $this->active;
	}

}
