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

            $relationFields = getRelationFields($newPage);

            // Checks if the relation field is present in the updated page
            foreach ($relationFields as $relation) {

                // Checks if the relation field was edited
                if (relationIsChanged($newPage, $oldPage, $relation)) {

                    // Getting Content type and page from the blueprint of the updated page
                    $relatedPage = page($newPage->blueprint()->field($relation)['relatedPage']);
                    $relationField = $newPage->blueprint()->field($relation)['relatationField'];
                    
                    // Getting the autoid value of the updated page
                    try {
                        $primaryKey = $newPage->AUTOID();
                    } catch (Throwable $e) {
                        throw new Exception('Many to Many Field Plugin: Updated page needs autoid field to create a relation.  '.$e->getMessage());
                    }

                    foreach ($newPage->$relation()->toStructure() as $singleRelation) {
                        // Fetching the related subpage
                        try {
                            $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $singleRelation->toArray()['foreignkey']);
                        } catch (Throwable $e) {
                            throw new Exception('Many to Many Field Plugin: "relatedPage" field in blueprint is missing. '.$e->getMessage());
                        }
                        // Changing the relation entry so it links to autoid of updated page
                        $singleRelationAtForeign = $singleRelation->toArray();
                        $singleRelationAtForeign['foreignkey'] = $primaryKey;
                        // Deleting the id field set by the toArray() Method
                        unset($singleRelationAtForeign['id']);
                        // Adding relation to foreign subpage
                        addRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                    }

                    // Filtering deleted keys
                    $oldForeignKeys = $oldPage->$relation()->toStructure();
                    $newForeignKeys = $newPage->$relation()->toStructure();
                    $deletedForeignKeys = [];
                    foreach($oldForeignKeys->toArray() as $oldForeignKey) {
                        if (!in_array($oldForeignKey, $newForeignKeys->toArray())) {
                            array_push($deletedForeignKeys, $oldForeignKey);
                        }
                    }
                    foreach ($deletedForeignKeys as $foreignKey) {
                        //Finding the related subpage
                        try {
                            $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $foreignKey['foreignkey']);
                        } catch (Throwable $e) {
                            throw new Exception('Many to Many Field Plugin: "relatedPage" field in blueprint is missing. '.$e->getMessage());
                        }
                        
                        //Chaning the relation-entry so it matches the entry at subpage
                        $singleRelationAtForeign = $foreignKey;
                        $singleRelationAtForeign['foreignkey'] = $primaryKey;

                        // Deleting the id field set by the toArray() Method
                        unset($singleRelationAtForeign['id']);
                        deleteRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                    }
                }
            }
        },

        'page.delete:before' => function ($status, $page) {

            $relationFields = getRelationFields($page);

            // Checks if the relation field is present in the updated page
            foreach ($relationFields as $relation) {

                // Getting autoids of related pages
                $foreignKeys = $page->$relation()->toStructure()->toArray();

                // Getting the autoid value of the deleted page
                $primaryKey = $page->AUTOID();

                // Getting related page and relation field from the blueprint of the deleted page
                $relatedPage = page($page->blueprint()->field($relation)['relatedPage']);
                $relationField = $page->blueprint()->field($relation)['relatationField'];

                foreach ($foreignKeys as $foreignKey) {

                    // Finding the related subpage
                    $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $foreignKey['foreignkey']);

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

function setID($value)
{
  $newValue = $value; 
  $newValue['id'] = 0;
  return $newValue;
}

function relationIsChanged($newPage, $oldPage, $relation)
{
    //Constructing safer strings for comparison
    $oldRelations = str_replace(["\n", "\r"], '', $oldPage->$relation()->toString());
    $newRelations = str_replace(["\n", "\r"], '', $newPage->$relation()->toString());
    
    $change = false;
    $oldRelationsArray =  $oldPage->$relation()->toStructure()->toArray();
    $oldRelationsArray = array_map('setID', $oldRelationsArray);
    $newRelationsArray =  $newPage->$relation()->toStructure()->toArray();
    $newRelationsArray = array_map('setID', $newRelationsArray);
    
    foreach($oldRelationsArray as $oldRelation) {
      if(!in_array($oldRelation, $newRelationsArray)) {
        $change = true;
      }
    }
    foreach($newRelationsArray as $newRelation) {
      if(!in_array($newRelation, $oldRelationsArray)) {
        $change = true;
      }
    }   
    return $change;
}

function addRelation($page, $value, $relationField)
{
    // Getting relations field from page to add to
    try {
        $fieldData = YAML::decode(page($page)->$relationField()->value());
    } catch (Throwable $e) {
        throw new Exception('Many to Many Field Plugin: related page or relatation field is faulty or missing. '.$e->getMessage());
    }

    // Getting Length of relations field before insert
    $fieldLengthBefore = count($fieldData);

    // Writing to relations field
    array_push($fieldData, $value);

    // Making array unique to filter out duplicates
    $fieldData = array_unique($fieldData, SORT_REGULAR);

    // Getting Length of relations field after insert
    $fieldLengthAfter = count($fieldData);
    
    // If fieldLengthAfter is same as fieldLengthBefore, nothing was added so we skip updating to avoid cascading
    if ($fieldLengthBefore !== $fieldLengthAfter) {
        try {
            page($page)->update([$relationField => YAML::encode($fieldData)]);

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
