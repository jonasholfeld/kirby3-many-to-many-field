****

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby3-many-2-many`.

### Git submodule

```
git submodule add https://github.com/jonasholfeld/kirby3-many-2-many.git site/plugins/kirby3-many-2-many
```

### Composer

```
composer require jonasholfeld/many-2-many
```

## Setup

### 1. Install AutoID

Add the [AutoID](https://github.com/bnomei/kirby3-autoid/) plugin by Bnomei to your kirby-project.

### 2. Use AutoID to identify your pages

Both blueprints of the pages you want to have a many-2-many relation need to have this field:

```yaml
autoid:
    type: hidden
    translate: false
```

The AutoID plugin automatically generates an unique ID for every page that will be created after it's install. If some pages already exist without an ID, you can force a [re-index](https://github.com/bnomei/kirby3-autoid/wiki/Force-Re-index).

### 3. Setup your blueprints

The many-2-many plugin gets all its information about the related pages from your blueprints, so its essential to set them up right. You can check out the [example blueprints](exampleBlueprints) to get a better idea about how to setup yours.

Both blueprints need a field of type relation, you can name then how you like:

```yaml
myRelationFromAtoB:
  type: relation
```

The relation field inherits from the structure field, so it is setup like a normal structure. It needs a couple of special fields with the right values to work, though.

### The foreignKey field

The foreignKey field is a multiselect that queries the related pages so you can search and select the page you want to link to. Its important to specify "page.autoid" as the value, so you use the unique ID field to identify pages.
Given you have a page called "works" with *work* subpages and you would like to link them to *artist* then your relation field should look like this:

```yaml
#site/blueprints/pages/work.yml

fields:
  theWorkToArtistRelation:
    type: relation
    fields:
      foreignKey:
        label: Linked Artists
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('artists').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
    validate:
      unique: theWorkToArtistRelation
```

The other blueprint should then look like this:

```yaml
#site/blueprints/pages/artist.yml

fields:
  theArtistToWorkRelation:
    type: relation
    fields:
      foreignKey:
        label: Linked Works
        type: multiselect
        min: 1
        max: 1
        options: query
        query:
          fetch: site.find('works').childrenAndDrafts
          text: "{{ page.title }}"
          value: "{{ page.autoid }}"
    validate:
      unique: theArtistToWorkRelation
```

You can add additional fields to your structure like described on the [kirby manual](https://getkirby.com/docs/reference/panel/fields/structure), just make sure they exist in both blueprints and are exactly the same. Do not change the min and max values.

### The validate field

Use the unique validator to make sure you do not have any duplicates in your relations:

```yaml
validate:
  unique: children
```

### The relation fields

There are three equally important fields you need to add to the relation field. They specify the related content type, the related page and the relation field in the other blueprint.

Given you have a page named "children" that has subpages with the type "child" in whose blueprint the relation field is called "parents" the relation field for the parent should look like this:

```yaml
children:
  label: Children
  type: relation
  fields:
    foreignKey:
      label: Children
      type: multiselect
      min: 1
      max: 1
      options: query
      query:
        fetch: site.find('children').childrenAndDrafts
        text: "{{ page.title }}"
        value: "{{ page.autoid }}"  
  validate:
    unique: children
  relatedContentType: child  #related Template
  relatedPage: children
  relatationField: parents
```

Vice Versa, the blueprint for the child-pages should look like this: 

```yaml
parents:
  label: Parents
  type: relation
  fields:
    foreignKey:
      label: Parent
      type: multiselect
      min: 1
      max: 1
      options: query
      query:
        fetch: site.find('parents').childrenAndDrafts
        text: "{{ page.title }}"
        value: "{{ page.autoid }}"  
  validate:
    unique: parents
  relatedContentType: parent
  relatedPage: parents
  relatationField: children
```

Once your blueprints are setup like this, the relation field changes on both sides, when there is an update from one of them.  

## License

MIT

## Credits

- [Jonas Holfeld](https://github.com/jonasholfeld)
