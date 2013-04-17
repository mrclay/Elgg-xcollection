# This plugin is deprecated.

Please use [elggx_collections_api](https://github.com/mrclay/Elgg-elggx_collections_api)

# xcollection

This is an experimental implementation of `ElggCollection`, a (will-be) native entity designed to store lists of integers optimized for filtering and/or ordering SQL queries of entities, annotations, or other tables with integer primary keys.

A collection has similarities to metadata; it's located by a string key, tied to a container entity, has its own access level, and can only be edited by users who can edit its container. Unlike metadata, to query or modify the collection's contents, you get a reference to its object and call methods on it. Also, due to its storage model, one can apply the collection in various ways to querying functions like `elgg_get_entities` in order to modify and/or order the resulting items.

## Creating/Modifying Collections

```php
<?php
// on login, make sure the user has a favorites collection.
// if already exists, this will just return false.
$user = elgg_get_logged_in_user_entity();
elgg_create_xcollection($user, 'faves');

// later... add something to it
$coll = elgg_get_xcollection($user, 'faves');
if ($coll) {
    $coll->unshiftItems($faveEntity); // will be stored as an int
}
```

## Applying Collections to Queries

To modify a query, you must specify the behavior of how a collection will affect it. The `elgg_xcollection_get_*_modifier` functions make it simple to apply the most common behaviors to collections. The objects these output (ElggXCollectionQueryModifier instances) can be placed into an array `$options['xcollections']` before passing `$options` to your query function.

In the following use, we use a collection to select the exact entities to be returned, so we don't need any additional options:

```php
<?php
$options = array(
    'xcollections' => elgg_xcollection_get_selector_modifier($user, 'faves'),
);
apply_xcollections_to_options($options);
echo elgg_list_entities($options);
```

Note the `apply_xcollections_to_options` function. This is currently required but will probably not be in a core implementation.

## Allowing Plugins to Modify Your Query

Via plugin hooks, you can simply allow any query to be modified by third parties.

```php
<?php
if (elgg_is_plugin_active('xcollection')) {
    elgg_xcollection_alter_entities_query($options, 'pages_group_widget', array('group' => $group_entity));
}
$content = elgg_list_entities($options);
```

Above, `elgg_xcollection_alter_entities_query` accepts your query options, a name describing where this query is used, and any other data you want passed in `$params`. Here, since this is a group widget, we want to give handlers a reference to the group so they can find collections on it.

## Modifying Queries via Plugin Hook

To modify the above query, we create a handler for the hook `apply, xcollection`. The handler checks for the query name it wants to affect in `$params['query_name']`, and optionally adds a query modifier to the `$returnvalue` array.

```php
<?php
elgg_register_plugin_hook_handler('apply', 'xcollection', 'my_handler');

function my_handler($hook, $type, $returnvalue, $params) {
    if ($params['query_name'] == 'pages_group_widget') {
        if (isset($params['group']) && $params['group'] instanceof ElggGroup) {
            $returnvalue[] = elgg_xcollection_get_sticky_modifier($params['group'], 'stickies');
        }
    }
    return $returnvalue;
};
```
This applies the group's "stickies" collection to the query such that items in the collection appear first.
