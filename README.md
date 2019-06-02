# Flex Directory Plugin

## About

The **Flex Directory** Plugin is for [Grav CMS](http://github.com/getgrav/grav).  It provides a simple plugin that 'out-of-the-box' acts as a simple user directory.  This plugin allows for CRUD operations via the admin plugin to easily manage large sets of data that don't fit as simple YAML configuration files, or Grav pages.  The example plugin comes with a dummy database of 500 entries which is a realistic real-world data set that you can experiment with.

![](assets/flex-directory-list.png)

![](assets/flex-directory-edit.png)

![](assets/flex-directory-options.png)

![](assets/flex-directory-compressor.gif)

## Installation

Typically a plugin should be installed via [GPM](http://learn.getgrav.org/advanced/grav-gpm) (Grav Package Manager):

```
$ bin/gpm install flex-directory
```

Alternatively it can be installed via the [Admin Plugin](http://learn.getgrav.org/admin-panel/plugins)

## Sample Data

Once installed you can either create entries manually, or you can copy the sample data set:

```shell
$ cp user/plugins/flex-directory/data/entries.json user/data/flex-directory/entries.json
```

## Configuration

This plugin works out of the box, but provides several fields that make modifying and extending this plugin easier:

```yaml
enabled: true
built_in_css: true
directories:
  companies: plugin://flex-directory/blueprints/flex-directory/companies.yaml
  contacts: plugin://flex-directory/blueprints/flex-directory/contacts.yaml
extra_admin_twig_path: 'theme://admin/templates'
extra_site_twig_path:
```

Simply edit the **Flex Directory** plugin options in the Admin plugin, or copy the `flex-directory.yaml` default file to your `user/config/plugins/` folder and edit the values there.   Read below for more help on what these fields do and how they can help you modify the plugin.

## Displaying

To display the directory simply add the following to our Twig template or even your page content (with Twig processing enabled):

```twig
{% include 'flex-directory/site.html.twig' %}
```

Alternatively just create a page called `flex-directory.md` or set the template of your existing page to `template: flex-directory`.  This will use the `flex-directory.html.twig` file provided by the plugin.  If this doesn't suit your needs, you can copy the provided Twig templates into your theme and modify them:


```shell
flex-directory/templates
├── flex-directory.html.twig
└── flex-directory
    └── ...
```

# Modifications

This plugin is configured with a few sample fields:

* published
* first_name
* last_name
* email
* website
* tags

These are probably not the exact fields you might want, so you will probably want to change them. This is pretty simple to do with Flex Directory, you just need to change the **Blueprints** and the **Twig Templates**.  This can be achieved simply enough by copying some current files and modifying them.

Let's assume you simply want to add a new "Phone Number" field to the existing Data and remove the "Tags".  These are the steps you would need to perform:

1. Copy the `blueprints/flex-directory/contacts.yaml` Blueprint file to another location, let's say `user/blueprints/flex-directory/` but really it could be anywhere (another plugin, your theme, etc.)

1. Edit the `user/blueprints/flex-directory/contacts.yaml` like so:

    ```yaml
    title: Contacts
    description: Simple contact directory with tags.
    type: flex-directory
    config:
    admin:
      list:
      title: name
      fields:
        published:
        width: 8
        last_name:
        link: edit
        first_name:
        link: edit
        company:
        email:
        website:
        tags:
    data:
      storage:
      type: file
      file: user://data/flex-directory/contacts.json
    form:
    validation: loose
    fields:
      published:
        type: toggle
        label: Published
        highlight: 1
        default: 1
        options:
          1: PLUGIN_ADMIN.YES
          0: PLUGIN_ADMIN.NO
        validate:
          type: bool
          required: true
      last_name:
        type: text
        label: Last Name
        validate:
          required: true
      first_name:
        type: text
        label: First Name
        validate:
          required: true
      company:
        type: text
        label: Company Name
      email:
        type: email
        label: Email Address
        validate:
          required: true
      website:
        type: url
        label: Web Site
      address.street1:
        type: text
        label: Address 1
      address.street2:
        type: text
        label: Address 2
      address.city:
        type: text
        label: City
      address.country:
        type: text
        label: Country
      address.state:
        type: text
        label: State or Province
      address.zip:
        type: text
        label: Postal / Zip Code
      tags:
        type: selectize
        size: large
        label: Tags
        classes: fancy
        validate:
          type: commalist
    ```

    Notice how we removed the `tags:` Blueprint field definition, and added a simple text field for `phone:`.  If you have questions about available form fields, [check out the extensive documentation](https://learn.getgrav.org/forms/blueprints/fields-available) on the subject.

1. Now we have to instruct the plugin to use this new blueprint rather then the default one provided with the plugin.  This is simple enough, just edit the **Blueprint Directory** option in the plugin configuration file `flex-directory.yaml` to point to: `user://data/blueprints/flex-directory`, and make sure you save it.


1. We need to copy the frontend Twig file and modify it to add the new "Phone" field.  By default your theme already has its `templates`. We'll simply copy the `user/plugins/flex-directory/templates/flex-directory/types/contacts.html.twig` file to `user/themes/quark/templates/flex-directory/types/contacts.html.twig`. Notice, there is no reference to `admin/` here, this is site template, not an admin one.

1. Edit the `contacts.html.twig` file you just copied so it has these modifications:

    ```twig
        <li>
            <div class="entry-details">
                {% if entry.website %}
                    <a href="{{ entry.website }}"><span class="name">{{ entry.last_name }}, {{ entry.first_name }}</span></a>
                {% else %}
                    <span class="name">{{ entry.last_name }}, {{ entry.first_name }}</span>
                {% endif %}
                {% if entry.email %}
                    <p><a href="mailto:{{ entry.email }}" class="email">{{ entry.email }}</a></p>
                {% endif %}
            </div>
        </li>
    ```

# File Upload

With Flex Directory v2.0, you can now utilize the `file` form field.  []The standard features apply](https://learn.getgrav.org/forms/blueprints/how-to-add-file-upload), and you can simply edit your custom blueprint with a field definition similar to:

```
    item_image:
      type: file
      label: Item Image
      random_name: true
      destination: 'user/data/flex-directory/files'
      multiple: true
```

# Advanced

You can radically alter the structure of the `countries.json` data file by making major edits to the `countries.yaml` blueprint file.  However, it's best to start with an empty `countries.json` if you are making wholesale changes or you will have data conflicts.  Best to create your blueprint first.  Reloading a **New Entry** until the form looks correct, then try saving, and check to make sure the stored `user/data/flex-directory/countries.json` file looks correct.

Then you will need to make more widespread changes to the admin and site Twig templates.  You might need to adjust the number of columns and the field names.  You will also need to pay attention to the JavaScript initialization in each template.

### Notes:

1. You can actually use pretty much any folder under the `user/` folder of Grav. Simply edit the **Extra Admin Twig Path** option in the `flex-directory.yaml` file.  It defaults to `theme://admin/templates` which means it uses the default theme's `admin/templates/` folder if it exists.
2. You can use any path for front end Twig templates also, if you don't want to put them in your theme, you can add an entry in the **Extra Site Twig Path** option of the `flex-directory.yaml` configuration and point to another location.
