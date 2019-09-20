<?php
namespace Grav\Plugin\FlexDirectory\Controllers;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Plugin\FlexDirectory\FlexType;

/**
 * Class AdminController
 * @package Grav\Plugin\FlexDirectory
 */
class AdminController extends SimpleController
{

    /**
     * Delete Directory
     */
    public function taskDelete()
    {
        $type = $this->target;
        $id = Grav::instance()['uri']->param('id');

        $directory = $this->getDirectory($type);
        $directory->remove($id);

        $status = $directory->save();

        if ($status) {
            $this->admin->setMessage($this->admin->translate(['PLUGIN_ADMIN.REMOVED_SUCCESSFULLY', 'Directory Entry']), 'info');
            $list_page = $this->location . '/' . $type;
            $this->setRedirect($list_page);

            Grav::instance()->fireEvent('gitsync');
        }
    }

    public function taskSave()
    {
        $type = $this->target;
        $id = Grav::instance()['uri']->param('id') ?: null;

        $directory = $this->getDirectory($type);

        foreach ($this->data as $key => $value) {
            if (Utils::startsWith($key, '_')) {
                unset ($this->data[$key]);
            }
        }

        // if no id param, assume new, generate an ID
        $object = $directory->update($this->data, $id);

        $status = $directory->save();

        if ($status) {
            $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SAVED'), 'info');

            if (!$this->redirect && !$id) {
                $edit_page = $this->location . '/' . $this->target . '/id:' . $object->getKey();
                $this->setRedirect($edit_page);
            }

            Grav::instance()->fireEvent('gitsync');
        }

        return $status;
    }

    protected function processPostEntriesSave($var)
    {
        switch ($var) {
            case 'create-new':
                $this->setRedirect($this->location . '/' . $this->target . '/action:add');
                $saved_option = $var;
                break;
            case 'list':
                $this->setRedirect($this->location . '/' . $this->target);
                $saved_option = $var;
                break;
            case 'edit':
            default:
                $this->setRedirect($this->location . '/' . $this->target . '/id:' . Grav::instance()['uri']->param('id'));
                $saved_option = 'edit';
                break;
        }

        $this->grav['session']->post_entries_save = $saved_option;
    }

    /**
     * Switch the content language. Optionally redirect to a different page.
     *
     */
    protected function taskSwitchlanguage()
    {
        if (!$this->authorizeTask('switch language', ['admin.flex-directory', 'admin.super'])) {
            return false;
        }
        $data = (array)$this->data;
        if (isset($data['lang'])) {
            $language = $data['lang'];
        } else {
            $language = $this->grav['uri']->param('lang');
        }
        if (isset($data['redirect'])) {
            $redirect = 'flex-directory/' . $data['redirect'];
        } else {
            $redirect = 'flex-directory';
        }
        if ($language) {
            $this->grav['session']->admin_lang = $language ?: 'en';
        }
        $this->admin->setMessage($this->admin->translate('PLUGIN_ADMIN.SUCCESSFULLY_SWITCHED_LANGUAGE'), 'info');

        $id = $this->grav['uri']->param('id');
        $this->setRedirect($this->location . '/' . $this->target . ($id ? '/id:'.$id : ''));
        return true;
    }

    /**
     * Dynamic method to 'get' data types
     *
     * @param $type
     * @param $id
     * @return mixed
     */
    protected function get($type, $id = null)
    {
        $collection = $this->getDirectory($type)->getCollection();

        return null !== $id ? $collection[$id] : $collection;
    }

}
