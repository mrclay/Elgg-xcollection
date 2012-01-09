Rename this folder to "xcollection" inside your Elgg "mod" folder.


== API

Use the `elgg_get/create_xcollection` functions to modify ElggXCollection objects.

    // create user's favorites collection (OK if it already exists, it'll return false)
    elgg_create_xcollection($user, 'faves');

    // add an entity to a user's favorites
    if ($coll = elgg_get_xcollection($user, 'faves')) {
        $coll->unshiftItems($faveEntity);
    }

To apply collections to queries, pass in instances of ElggXQueryModifier under the options key `xcollections`. The functions `elgg_xcollection_get_*_modifier` combine finding the collection and wrapping it with the query modifier.

    // show faves list
    $options = array(
        'xcollections' => elgg_xcollection_get_selector_modifier($user, 'faves'),
    );
    apply_xcollections_to_options($options);
    echo elgg_list_entities($options);


    // sort faves to the top of any query where they might appear
    $options = array(
        'type' => 'object',
        'subtype' => 'blog',
        'xcollections' => elgg_xcollection_get_sticky_modifier($user, 'faves'),
    );
    apply_xcollections_to_options($options);
    echo elgg_list_entities($options);

