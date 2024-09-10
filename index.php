<?php

Kirby::plugin('jonasholfeld/many-to-many-field', [
    'fields' => [
        'manytomany' => [
            'extends' => 'structure',
        ],
    ],
    'validators' => [
        'unique' => function ($value, $field) {
            return count($value) == count(array_unique($value, SORT_REGULAR));
        },
    ],
    'hooks' => [
        'page.update:after' => function ($newPage, $oldPage) {
            $primaryKey = $newPage->uuid()->id();
            $relationFields = getRelationFields($newPage);
            foreach ($relationFields as $relation) {
              $relationField = $newPage->blueprint()->field($relation)['relatationField'];
              $oldRelationsArray =   YAML::decode($oldPage->$relation()->value());
              $newRelationsArray =  YAML::decode($newPage->$relation()->value());
              // Looping over old relations to find deleted ones...
              foreach($oldRelationsArray as $oldRelation) {
                if(!in_array($oldRelation, $newRelationsArray)) {
                    // an old relation was deleted...
                    try {
                        $foreign_subPage = kirby()->page($oldRelation['foreignkey']);
                    } catch (Throwable $e) {
                        throw new Exception('Many to Many Field Plugin: Trying to fetch non-existing page...'.$e->getMessage());
                        continue;
                    }
                    // Changing the old relation so it corresponds with the relation at the foreign page...
                    $singleRelationAtForeign = $oldRelation;
                    $singleRelationAtForeign['foreignkey'] = "page://".$primaryKey;
                    // Deleting the old relation at the foreign page
                    deleteRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                }
              }
              // looping over new relations to find added ones
              foreach($newRelationsArray as $newRelation) {
                if(!in_array($newRelation, $oldRelationsArray)) {
                  // a new relation was added...
                  try {
                      $foreign_subPage = kirby()->page($newRelation['foreignkey']);
                  } catch (Throwable $e) {
                      throw new Exception('Many to Many Field Plugin: Trying to fetch non-existing page...'.$e->getMessage());
                      continue;
                  }
                  // Changing the new relation so it corresponds with the relation at the foreign page...
                  $singleRelationAtForeign = $newRelation;
                  $singleRelationAtForeign['foreignkey'] = "page://".$primaryKey;
                  // Adding the new relation to the foreign page
                  addRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                }
              }
            }
        },
        'page.delete:before' => function ($status, $page) {
            $relationFields = getRelationFields($page);
            // Checks if the relation field is present in the updated page
            foreach ($relationFields as $relation) {
                // Getting the relations of the deleted page...
                $relations = YAML::decode($page->$relation()->value());
                $relationField = $page->blueprint()->field($relation)['relatationField'];
                // Getting the uuid of the deleted page
                $primaryKey = $page->uuid();
                foreach ($relations as $foreignKey) {
                    // Finding the related subpage
                    $foreign_subPage = kirby()->page($foreignKey['foreignkey']);
                    // Changing the relation-entry so it matches the entry at subpage
                    $singleRelationAtForeign = $foreignKey;
                    $singleRelationAtForeign['foreignkey'] = $primaryKey;
                    // Deleting the relation entry from the related page
                    deleteRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                }
            }
        },
    ],
]);

function getRelationFields($page)
{
    $relationFields = [];
    foreach ($page->blueprint()->fields() as $field) {
        if ($field['type'] == 'manytomany') {
            array_push($relationFields, $field['name']);
        }
    }
    return $relationFields;
}

function deleteRelation($page, $value, $relationField)
{
    // Getting relations field from page to delete from
    $fieldData = YAML::decode($page->$relationField()->value());
    // Creating empty field
    $newFieldData = [];
    // Pushing all entries that dont match the deleted relation 
    foreach ($fieldData as $relation) {
        $singleRelation = $relation;
        if ($singleRelation != $value) {
            array_push($newFieldData, $singleRelation);
        }
    }
    // Encoding
    try {
        // Updating page
        $page->update([$relationField => $newFieldData]);
    } catch (Exception $e) {
        return $e->getMessage();
    }
}


function addRelation($page, $value, $relationField)
{
    try {
        $fieldData = YAML::decode($page->$relationField()->value());
    } catch (Throwable $e) {
        throw new Exception('Many to Many Field Plugin: related page or relatation field is faulty or missing. ' .$e->getMessage());
    }
    // Writing to relations field
    array_push($fieldData, $value);
    if($page->isLocked()) {
        throw new Exception('Related page is current locked. Save or delete all unsaved changes on the linked page.');
    } else {
        $page->update([$relationField => YAML::encode($fieldData)]);
        return true;
    }
}
