# Kirby 3 Many To Many Field

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

## Setup

1. [Install AutoID](#1-Install-AutoID)
2. [Use AutoID to identify your pages](#2-Use-AutoID-to-identify-your-pages)
3. [Setup your blueprints](#3-Setup-your-blueprints)
- 3.1 [Naming and Type](#31-Naming-and-Type)
- 3.2 [The foreignkey field](#32-The-foreignkey-field)
- 3.3 [The unique validator](#33-The-unique-validator)
- 3.4 [The relation fields](#34-The-relation-fields)
- 3.5 [Corresponding blueprint](#35-Corresponding-blueprint)
- 3.6 [Additional structure fields](#36-Additional-structure-fields)

### 1. Install AutoID

Add the [AutoID](https://github.com/bnomei/kirby3-autoid/) plugin by Bnomei to your kirby-project.

### 2. Use AutoID to identify your pages

Both blueprints of the pages you want to have a many-2-many relation need to have this field:

```yaml
autoid:
  type: hidden
  translate: false
```

The AutoID plugin automatically generates a unique ID for every page that will be created after it’s install. If some pages already exist without an ID, you can force a [re-index](https://github.com/bnomei/kirby3-autoid/wiki/Force-Re-index).

### 3. Setup your blueprints

The many-to-many plugin gets all its information about the related pages from your blueprints, so it’s essential to set them up right. You can check out the [example blueprints](exampleBlueprints) to get a better idea about how to setup yours.

Both blueprints need the manytomany field in order to connect the pages correctly. As it’s important to set them up correctly, the following text explains every step bit by bit.

#### 3.1 Naming and Type

You can name the field how you like. A name hinting to the nature of the relation or the templates of the related pages might be helpful.

You need to specify the type as *manytomany*:

```yaml
myRelatedPages: #<-- name how you like
  type: manytomany
```

The manytomany-field inherits from the [structure field](https://getkirby.com/docs/reference/panel/fields/structure), so it is setup like a normal structure-field with a couple of additional fields that need to be filled.

#### 3.2 The foreignkey field

The foreignkey field is the field inside our manytomany-field that saves the "foreign keys". In our case they are the IDs created by the autoID plugin. You create a field inside the manytomany-field called "foreignkey" that is a multiselect that queries its options from the pages you would like to link to. To be more specific, it queries the children of a given page, so you need to specify the name of the parent-page to whose subpages you would like to link to.
It is important to use *page.autoid* as the value, you can chose what to use as the text, but I recommend to use *page.title* to identify the pages.

```yaml
myRelatedPages:
  type: manytomany
  lable: My Related Pages
  fields:
    foreignkey: #<-- name needs to be *foreignkey*
      label: Related Page
      type: multiselect
      min: 1
      max: 1
      options: query
      query:
        fetch: site.find('myRelatedParentPage').childrenAndDrafts # <-- use name of parent-page of related pages here
        text: "{{ page.title }}"
        value: "{{ page.autoid }}"
  validate:
    unique: theWorkToArtistRelation
```

#### 3.3 The unique validator

Duplicate entries inside the manytomany field cause problems, so make sure to use the unique validator of the plugin:

```yaml
myRelatedPages:
  type: manytomany
  lable: My Related Pages
  fields:
    foreignkey:
      label: Related Page
      type: multiselect
      min: 1
      max: 1
      options: query
      query:
        fetch: site.find('myRelatedParentPage').childrenAndDrafts
        text: "{{ page.title }}"
        value: "{{ page.autoid }}"
  validate:
    unique: myRelatedPages #<-- use name of your field
```

#### 3.4 The relation fields

There are three equally important fields you need to add to the manytomany field. They specify the template of the related pages, their parent-page and the name of the corresponding manytomany field in their blueprint. Make sure to fill them out correctly.

```yaml
myRelatedPages:
  type: manytomany
  lable: My Related Pages
  fields:
    foreignkey:
      label: Related Page
      type: multiselect
      min: 1
      max: 1
      options: query
      query:
        fetch: site.find('myRelatedParentPage').childrenAndDrafts
        text: "{{ page.title }}"
        value: "{{ page.autoid }}"
  validate:
    unique: myRelatedPages
  relatedTemplate: myRelatedTemplate #<-- name of the template of the linked pages
  relatedPage: myRelatedFolder #<-- name of the parent-page of the linked pages
  relatationField: myOtherRelatedPages  #<-- name of the corresponding manytomany-field in the blueprint of linked pages
```

#### 3.5 Corresponding blueprint

To be able to edit the relation from both sides, both blueprints of the related pages need to have a field of the type manytomany. They need to have corresponding values in the specific fields. Here is an example of two blueprints, in this case with a relation between students and schools.

#### **`school.yml`**
```yaml
title: School
fields: 
  students:
    type: manytomany
    label: Students
    fields:
      foreignkey:
        label: Student
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('students').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
    validate:
      unique: students
    relatedTemplate: student
    relatedPage: students
    relatationField: schools
  autoid:
    type: hidden
    translate: false  
```

#### **`student.yml`**
```yaml
title: School
fields: 
  students:
    type: manytomany
    label: Students
    fields:
      foreignkey:
        label: Student
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('students').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
      year: #<-- some extra field
        type: number
    validate:
      unique: students
    relatedTemplate: student
    relatedPage: students
    relatationField: schools
  autoid:
    type: hidden
    translate: false 
```

Once your blueprints are setup like this, the manytomany field changes on both sides, when there is an update from one of them.

#### 3.6 Additional structure fields

As mentioned above, the manytomany field is just a structure field with some special fields. That means you can add any number of fields to the structure, if you need to save some extra information about the relation, e.g. a year. Just make sure the two linked blueprints both have the extra fields in the manytomany field:

#### **`school.yml`**
```yaml
title: School
fields: 
  students:
    type: manytomany
    label: Students
    fields:
      foreignkey:
        label: Student
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('students').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
      year: #<-- some extra field
        type: number
    validate:
      unique: students
    relatedTemplate: student
    relatedPage: students
    relatationField: schools
  autoid:
    type: hidden
    translate: false 
```

#### **`student.yml`**
```yaml
title: Student
fields: 
  schools:
    type: manytomany
    label: Schools
    fields:
      foreignkey:
        label: School
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('schools').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
      year: #<-- the same extra field
        type: number    
    validate:
      unique: schools
    relatedTemplate: school
    relatedPage: schools
    relatationField: students
  autoid:
    type: hidden
    translate: false  
```

## License

MIT

## Credits

- [Jonas Holfeld](https://github.com/jonasholfeld)
- Developed during my internship and with the friendly support of [Christoph Knoth](https://github.com/christophknoth) and Konrad Renner at [Knoth und Renner](http://knoth-renner.com/)
