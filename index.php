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
              $oldRelationsArray =  $oldPage->$relation()->toStructure()->toArray();
              $oldRelationsArray = array_map('unSetID', $oldRelationsArray);
              $newRelationsArray =  $newPage->$relation()->toStructure()->toArray();
              $newRelationsArray = array_map('unSetID', $newRelationsArray);
              foreach($oldRelationsArray as $oldRelation) {
                if(!in_array($oldRelation, $newRelationsArray)) {
                    try {
                        $foreign_subPage = kirby()->page($oldRelation['foreignkey']);
                    } catch (Throwable $e) {
                        throw new Exception('Many to Many Field Plugin: "relatedPage" field in blueprint is missing. '.$e->getMessage());
                    }
                    $singleRelationAtForeign = $oldRelation;
                    $singleRelationAtForeign['foreignkey'] = "page://".$primaryKey;
                    unset($singleRelationAtForeign['id']);
                    deleteRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                }
              }
              foreach($newRelationsArray as $newRelation) {
                if(!in_array($newRelation, $oldRelationsArray)) {
                  try {
                      $foreign_subPage = kirby()->page($newRelation['foreignkey']);
                  } catch (Throwable $e) {
                      throw new Exception('Many to Many Field Plugin: "relatedPage" field in blueprint is missing. '.$e->getMessage());
                  }
                  $singleRelationAtForeign = $newRelation;
                  $singleRelationAtForeign['foreignkey'] = "page://".$primaryKey;
                  unset($singleRelationAtForeign['id']);
                  addRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                }
              }
            }
        },
        'page.delete:before' => function ($status, $page) {
            $relationFields = getRelationFields($page);
            // Checks if the relation field is present in the updated page
            foreach ($relationFields as $relation) {
                // Getting bosst-ids of related pages
                $foreignKeys = $page->$relation()->toStructure()->toArray();
                // Getting the boost-id value of the deleted page
                $primaryKey = $page->uuid();
                // Getting related page and relation field from the blueprint of the deleted page
                $relatedPage = kirby()->page($page->blueprint()->field($relation)['relatedPage']);
                $relationField = $page->blueprint()->field($relation)['relatationField'];
                foreach ($foreignKeys as $foreignKey) {
                    // Finding the related subpage
                    $foreign_subPage = kirby()->page($foreignKey['foreignkey']);
                    // Changing the relation-entry so it matches the entry at subpage
                    $singleRelationAtForeign = $foreignKey;
                    $singleRelationAtForeign['foreignkey'] = $primaryKey;
                    // Deleting the id field set by the toArray() Method
                    unset($singleRelationAtForeign['id']);
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
    $fieldData = $page->$relationField()->toStructure()->toArray();
    // Creating empty field
    $newFieldData = [];
    // Pushing all entries that dont match the deleted relation 
    foreach ($fieldData as $relation) {
        $singleRelation = $relation;
        unset($singleRelation['id']);
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

function unSetID($value)
{
  $newValue = $value; 
  $newValue['id'] = 0;
  return $newValue;
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
