<?php

Kirby::plugin('jonasholfeld/many-2-many', [
    'fields' => [
        'relation' => [
            'extends' => 'structure',
        ],
    ],
    'validators' => [
        'unique' => function ($value, $field) {
            $values = array_column(YAML::decode($value));

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
                    $relatedContentType = $newPage->blueprint()->field($relation)['relatedContentType'];
                    $relatedPage = page($newPage->blueprint()->field($relation)['relatedPage']);
                    $relationField = $newPage->blueprint()->field($relation)['relatationField'];
                    // Getting the autoid value of the updated page
                    $primaryKey = $newPage->AUTOID();
                    foreach ($newPage->$relation()->toStructure() as $singleRelation) {
                        // Fetching the related subpage
                        $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $singleRelation->toArray()['foreignkey']);
                        // Changing the relation entry so it links to autoid of updated page
                        $singleRelationAtForeign = $singleRelation->toArray();
                        $singleRelationAtForeign['foreignkey'] = $primaryKey;
                        // Deleting the id field set by the toArray() Method
                        unset($singleRelationAtForeign['id']);
                        // Adding relation to foreign subpage
                        addRelation($foreign_subPage, $singleRelationAtForeign, $relationField);
                    }
                    $oldForeignKeys = $oldPage->$relation()->toStructure();
                    $newForeignKeys = $newPage->$relation()->toStructure();
                    $deletedForeignKeys = array_filter($oldForeignKeys->toArray(), fn ($oldForeignKey) => !in_array($oldForeignKey, $newForeignKeys->toArray()));
                    foreach ($deletedForeignKeys as $foreignKey) {
                        //Finding the related subpage
                        $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $foreignKey['foreignkey']);
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
                $foreignKeys = $page->$relation()->toStructure()->toArray();
                $primaryKey = $page->AUTOID();
                $relatedPage = page($page->blueprint()->field($relation)['relatedPage']);
                $relationField = $page->blueprint()->field($relation)['relatationField'];
                foreach ($foreignKeys as $foreignKey) {
                    //Finding the related subpage
                    $foreign_subPage = $relatedPage->childrenAndDrafts()->findBy('autoid', $foreignKey['foreignkey']);
                    //Changing the relation-entry so it matches the entry at subpage
                    $singleRelationAtForeign = $foreignKey;
                    $singleRelationAtForeign['foreignkey'] = $primaryKey;
                    // Deleting the id field set by the toArray() Method
                    unset($singleRelationAtForeign['id']);
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
        if ($field['type'] == 'relation') {
            array_push($relationFields, $field['name']);
        }
    }

    return $relationFields;
}

function deleteRelation($page, $value, $relationField)
{
    // Getting relations field from page to delete from
    $fieldData = $page->$relationField()->toStructure()->toArray();
    $newFieldData = [];
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

function relationIsChanged($newPage, $oldPage, $relation)
{
    $oldRelations = str_replace(["\n", "\r"], '', $oldPage->$relation()->toString());
    $newRelations = str_replace(["\n", "\r"], '', $newPage->$relation()->toString());

    return $newRelations != $oldRelations;
}

function addRelation($page, $value, $relationField)
{
    // Getting relations field from page to add to
    $fieldData = YAML::decode(page($page)->$relationField()->value());
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
