<?php
/**
 * Elgg river item wrapper.
 * Wraps all river items.
 *
 * @todo: Clean up this logic.
 * It looks like this only will allow comments on non user and non group forum
 * topic entities.
 *
 * Different chunks are used depending on if comments exist or not.
 *
 *
 */

$object = get_entity($vars['item']->object_guid);
$object_url = $object->getURL();
$likes_count = elgg_count_likes($object);

//user
//if displaying on the profile get the object owner, else the subject_guid
if (get_context() == 'profile' && $object->getSubtype() ==  'thewire') {
	$user = get_entity($object->owner_guid);
} else {
	$user = get_entity($vars['item']->subject_guid);
}

// get last three comments display
// want the 3 most recent comments (order by time_created desc = 3 2 1)
// but will display them with the newest at the bottom (1 2 3)
if ($comments = get_annotations($vars['item']->object_guid, "", "", 'generic_comment', "", "", 3, 0, "desc")) {
	$comments = array_reverse($comments);
}

// for displaying "+N more"
// -3 from the count because the 3 displayed don't count in the "more"
$comment_count = count_annotations($vars['item']->object_guid, $vars['item']->type, $vars['item']->subtype, 'generic_comment');
if ($comment_count < 3) {
	$more_comments_count = 0;
} else {
	$more_comments_count = $comment_count - 3;
}

?>
<div class="river_item riverdashboard" id="river_entity_<?php echo $object->guid; ?>">
	<span class="river_item_useravatar">
		<?php echo elgg_view("profile/icon",array('entity' => $user, 'size' => 'small')); ?>
	</span>

	<div class="river_item_contents clearfloat">
<?php

// body contents, generated by the river view in each plugin
echo $vars['body'];

// display latest 3 comments if there are any
if ($comments){
	$counter = 0;

	echo "<div class='river_comments_tabs clearfloat'>";
	echo "<a class='river_more_comments show_comments_button link'>" . elgg_echo('comments') . '</a>';

	if ($likes_count != 0) {
		echo elgg_view('likes/forms/display', array('entity' => $object));
	}

	echo "</div>"; // close river_comments_tabs

	echo "<div class='river_comments'>";

	if ($likes_count != 0) {
		//show the users who liked the object
		echo "<div class='likes_list hidden'>";
		echo list_annotations($object->getGUID(), 'likes', 99);
		echo "</div>";
	}

	echo "<div class=\"comments_container\">";
	// display appropriate comment link
	if ($more_comments_count > 0) {
		echo "<a class=\"river_show_more_comments link\">" .
		sprintf(elgg_echo('riverdashbardo:n_more_comments'), $more_comments_count) . '</a>';
	}
	echo "<div class=\"comments_list\">";
	foreach ($comments as $comment) {
		//get the comment owner
		$comment_owner = get_user($comment->owner_guid);
		//get the comment owner's profile url
		$comment_owner_url = $comment_owner->getURL();
		// color-code each of the 3 comments
		// @todo this isn't used in CSS...
		if( ($counter == 2 && $comment_count >= 4) || ($counter == 1 && $comment_count == 2) || ($counter == 0 && $comment_count == 1) || ($counter == 2 && $comment_count == 3) ) {
			$alt = 'latest';
		} else if( ($counter == 1 && $comment_count >= 4) || ($counter == 0 && $comment_count == 2) || ($counter == 1 && $comment_count == 3) ) {
			$alt = 'penultimate';
		}
		//display comment
		echo "<div class='river_comment $alt clearfloat'>";
		echo "<span class='river_comment_owner_icon'>";
		echo elgg_view("profile/icon", array('entity' => $comment_owner, 'size' => 'tiny'));
		echo "</span>";

		//truncate comment to 150 characters and strip tags
		$contents = elgg_make_excerpt($comment->value, 150);

		echo "<div class='river_comment_contents'>";
		echo "<a href=\"{$comment_owner_url}\">" . $comment_owner->name . "</a> " . parse_urls($contents);
		echo "<span class='entity_subtext'>" . friendly_time($comment->time_created) . "</span>";
		echo "</div></div>";
		$counter++;
	}
	echo elgg_make_river_comment($object);

	echo '</div>'; // close comments_list.

	echo "</div></div>"; // close comments_container and river_comments
} else {
	// tab bar nav - for users that liked object
	if ($vars['item']->type != 'user' && $likes_count != 0) {
		echo "<div class='river_comments_tabs clearfloat'>";
	}

	if ($likes_count != 0) {
		echo elgg_view('likes/forms/display', array('entity' => $object));
	}

	if ($vars['item']->type != 'user' && $likes_count != 0) {
		echo "</div>"; // close river_comments_tabs
	}

	if ($vars['item']->type != 'user') {
		echo "<div class='river_comments'>";
	}
	if ($likes_count != 0) {
		//show the users who liked the object
		echo "<div class='likes_list hidden'>";
		echo list_annotations($object->getGUID(), 'likes', 99);
		echo "</div>";
	}

	// if there are no comments to display
	// and this is not a user or a group discussion entry - include the inline comment form
	if ($vars['item']->type != 'user' && $vars['item']->subtype != 'groupforumtopic') {
		echo elgg_make_river_comment($object);
	}
	if ($vars['item']->type != 'user') {
		echo "</div>";
	}
}
?>
	</div>
</div>