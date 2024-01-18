# Kirby 3 Many To Many Field

> Version 2.0 of this plugin uses the [Unique IDs](https://getkirby.com/docs/guide/uuids) (aka UUIDs) that are part of the Kirby core since Kirby 3.8.0. Make sure to only use it with Kirby 3.8.0 or higher (doesn't work with Kirby 4 though). Upgrading from 1.0 to 2.0 with existing content is not possible and will lead to corrupted data.

This plugin allows you to create many-to-many relationships between pages in Kirby. The relationship is bidirectional, meaning it can be edited from either side and is automatically updated on the other side. The relationship can have attributes that can be updated from both sides as well. You can define multiple many-to-many relations on one page. If a page with a relation to one or many other pages gets deleted, all relations to this page get deleted as well.

You need to install the [AutoId plugin](https://github.com/bnomei/kirby3-autoid) by Bnomei to your project as well for this plugin to work.

This plugin uses two hooks: the [page.update:after](https://getkirby.com/docs/reference/plugins/hooks/page-update-after) and the [page.delete:before](https://getkirby.com/docs/reference/plugins/hooks/page-delete-before) hook. If you use these hooks in your project as well, make sure to rename the hooks and trigger them seperately as described [here](https://getkirby.com/docs/reference/plugins/extensions/hooks#creating-your-own-hooks).

![many-to-many-kirby3](https://user-images.githubusercontent.com/282518/122782365-f3b90c80-d2b0-11eb-8428-9b4efecbc713.jpg)

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby3-many-to-many-field`.

### Git submodule

```
git submodule add https://github.com/jonasholfeld/kirby3-many-to-many-field.git site/plugins/kirby3-many-to-many-field
```

### Composer

```
composer require jonasholfeld/kirby3-many-to-many-field
```

## Setup your blueprints

The many-to-many plugin gets all its information about the related pages from your blueprints, so it’s essential to set them up right. You can check out the [example blueprints](exampleBlueprints) to get a better idea about how to setup yours.

Both blueprints need the manytomany field in order to connect the pages correctly. As it’s important to set them up correctly, the following text explains every step bit by bit.

1. [Quickstart](#1-Quickstart ) 
2. [Setup in Detail](#-2-Setup-in-Detail)
2.1 [Necessery structure fields](#31-Necessary-Structure-Fields)
2.2 [Additional structure fields](#36-Additional-structure-fields)
2.3 [How to use in templates](#37-How-to-use-in-templates)

#### 1 Quickstart 

You can use and adjust these two blueprints to setup a relation between two pages with the plugin. It implements the classic Employee <--> Project relation you might know from database examples (see ER-diagram above). Make sure to rename all fields according to your situation. To fully understand all the fields and adjust them to your situation you should read on. 

#### **`project.yml`**

```yaml
title: Project

fields:
  description:
    type: text
    label: Description
  employees:
    type: manytomany
    label: Employees
    fields:
      foreignkey:
        label: Employee
        type: select
        options: query
        query:
          fetch: site.find('employees').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.uuid }}"
      hours:
        type: number
        label: Number of hours
    validate:
      unique: employees
    relatationField: projects
```

#### **`employee.yml`**

```yaml
title: Employee

fields:
  age:
    type: number
    label: Age
  projects:
    type: manytomany
    label: Projects
    fields:
      foreignkey:
        label: Project
        type: select
        options: query
        query:
          fetch: site.find('projects').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.uuid }}"
      hours:
        type: number
        label: Number of hours
    validate:
      unique: projects
    relatationField: employees
```

## 2 Setup in Detail

#### 2.1 Necessary Structure Fields

Let's go through above's example step by step and look at the neccesary fields.

You can name the relation field how you like. A name hinting to the nature of the relation or the templates of the related pages might be helpful.

You need to specify the type as *manytomany*:

```yaml
employees: #<-- name how you like
  type: manytomany
...
```

The manytomany-field inherits from the [structure field](https://getkirby.com/docs/reference/panel/fields/structure), so it is setup like a normal structure-field with a couple of additional fields that need to be filled.

```yaml
fields:
  foreignkey: #<-- must be called like this
    label: Employee
    type: select #<-- must be a select field
    options: query 
    query:
      fetch: site.find('employees').childrenAndDrafts #<-- adjust to your needs...
      text: "{{ page.title }}"
      value: "{{ page.uuid }}"
...
```

The first necessary field is called "foreignkey" and saves the ID of the related page. It is a select field that fetches its options from a query. Adjust this to your needs, but dont change the name of the field.

```yaml
validate:
  unique: projects
relatationField: employees
...
```

The other two necessary fields are a validator that makes sure you link a page only once to another, and a static field that saves the name of the corresponding relation field, that is the field in the linked page the relation should be written to. This is needed because there could be multiple relation fields in the same blueprint and the plugin needs to know which relation should be written to which field.

#### 2.2 Corresponding blueprint

To be able to edit the relation from both sides, both blueprints of the related pages need to have a field of the type manytomany. They need to have corresponding values in the specific fields. Lets visit aboves example again and look how the fields are corresponding...

#### **`project.yml`**
```yaml
title: Project

fields:
  description:
    type: text
    label: Description
  employees: #<-- name of the related entities...
    type: manytomany
    fields:
      foreignkey:
        label: Employee
        type: select
        options: query
        query:
          fetch: site.find('employees').childrenAndDrafts #<-- query to the related entities...
          text: "{{ page.title }}"
          value: "{{ page.uuid }}"
      hours:
        type: number
        label: Number of hours
    validate:
      unique: employees #<-- name of the manytomany field to be validated
    relatationField: projects #<-- name of the corresponding relation field
```

#### **`employee.yml`**

```yaml
title: Employee

fields:
  age:
    type: number
    label: Age
  projects: #<-- name of the related entities...
    type: manytomany
    label: Projects
    fields:
      foreignkey:
        label: Project
        type: select
        options: query
        query:
          fetch: site.find('projects').childrenAndDrafts #<-- query to the related entities...
          text: "{{ page.title }}"
          value: "{{ page.uuid }}"
      hours:
        type: number
        label: Number of hours
    validate:
      unique: projects #<-- name of the manytomany field to be validated
    relatationField: employees #<-- name of the corresponding relation field
```

Once your blueprints are setup like this, the manytomany field changes on both sides, when there is an update from one of them.

#### 3.6 Additional structure fields

As mentioned above, the manytomany field is just a structure field with some special fields. That means you can add any number of fields to the structure, if you need to save some extra information about the relation, e.g. the number of hours an employee worked on a project (like in the example above). Just make sure the two linked blueprints both have the extra fields in the manytomany field like seen above with the additional "hours" field.

### 3.7 How to use in templates

#### **`employee.php`**
```php
<h1>Projects</h1>
<?php
// using the `toStructure()` method, we create a structure collection from the manytomany-field
$projects = $page->projects()->toStructure();
// we can then loop through the entries and render the individual fields
foreach($projects as $project):
    // Fetching the project page by using the page method
    $projectPage = kirby()->page($project->foreignkey()); ?>
    <!-- Getting the title from the related page  -->
    <h2>Title: <?= $projectPage->title() ?></h2>
    <!-- Getting the hours from the structure entrie -->
    <h2>Hours: <?= $project->hours() ?></h2>
<?php endforeach; ?>
```

## License

MIT

## Credits

- [Jonas Holfeld](https://github.com/jonasholfeld)
- Developed during my internship and with the friendly support of [Christoph Knoth](https://github.com/christophknoth) and Konrad Renner at [Knoth & Renner](https://knoth-renner.com/)
