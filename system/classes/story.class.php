<?php

/* Reminder: always indent with 4 spaces (no tabs). */
// +---------------------------------------------------------------------------+
// | Geeklog 2.0                                                               |
// +---------------------------------------------------------------------------+
// | story.class.php                                                           |
// |                                                                           |
// | Geeklog Story Abstraction.                                                |
// +---------------------------------------------------------------------------+
// | Copyright (C) 2006-2013 by the following authors:                         |
// |                                                                           |
// | Authors: Michael Jervis, mike AT fuckingbrit DOT com                      |
// +---------------------------------------------------------------------------+
// |                                                                           |
// | This program is free software; you can redistribute it and/or             |
// | modify it under the terms of the GNU General Public License               |
// | as published by the Free Software Foundation; either version 2            |
// | of the License, or (at your option) any later version.                    |
// |                                                                           |
// | This program is distributed in the hope that it will be useful,           |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
// | GNU General Public License for more details.                              |
// |                                                                           |
// | You should have received a copy of the GNU General Public License         |
// | along with this program; if not, write to the Free Software Foundation,   |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.           |
// |                                                                           |
// +---------------------------------------------------------------------------+

/**
 * This file provides a class to represent a story, or article. It provides a
 * finite state machine for articles. Switching them between the various states:
 *  1) Post Data
 *  2) Display Mode
 *  3) Edit Mode
 *  4) Database Mode
 *
 * @package Geeklog
 * @filesource
 * @version 0.1
 * @since GL 1.4.2
 * @copyright Copyright &copy; 2006-2009
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 * @author Michael Jervis, mike AT fuckingbrit DOT com
 *
 */

require_once 'gltext.class.php';

/**
 * Constants for stories:
 * Loading from database:
 */
define('STORY_LOADED_OK', 1);

define('STORY_INVALID_SID', -1);
define('STORY_PERMISSION_DENIED', -2);
define('STORY_EDIT_DENIED', -3);

/**
 * Constants for Stories:
 * Saving to database
 */
define('STORY_SAVED', 2);
define('STORY_SAVED_SUBMISSION', 3);

/**
 * Constants for Stories:
 * Loading from request.
 */
define('STORY_DUPLICATE_SID', -4);
define('STORY_EXISTING_NO_EDIT_PERMISSION', -5);
define('STORY_NO_ACCESS_PARAMS', -6);
define('STORY_EMPTY_REQUIRED_FIELDS', -7);
define('STORY_NO_ACCESS_TOPIC', -8);

/**
  * Constants for our magic loader
  */
define('STORY_AL_ALPHANUM', 0);
define('STORY_AL_NUMERIC', 1);
define('STORY_AL_CHECKBOX', 2);
define('STORY_AL_ANYTHING', 3);

class Story
{
    //*************************************************************************/
    // Variables:

    // Public
    /**
     * Mode, either 'admin' for in the admin screens, or submission, for
     * when the user is using submit.php. Controls tons of stuff.
     */
    var $mode = 'admin';

    /**
     * Type of story. User submission or normal editor entered stuff.
     * Will be 'submission' if it's from the submission queue.
     */
    var $type = 'article';

    //Private

    /**
     * PRIVATE MEMBER VARIABLES: Things that make up a story.
     */
    var $_sid;
    var $_title;
    var $_page_title;
    var $_meta_description;
    var $_meta_keywords;
    var $_introtext;
    var $_bodytext;
    var $_text_version;
    var $_postmode;
    var $_uid;
    var $_draft_flag;
    var $_date;
    var $_hits;
    var $_numemails;
    var $_comment_expire;
    var $_comments;
    var $_trackbacks;
    var $_related;
    var $_featured;
    var $_show_topic_icon;
    var $_commentcode;
    var $_trackbackcode;
    var $_statuscode;
    var $_expire;
    var $_advanced_editor_mode;
    var $_frontpage;
    var $_cache_time;
    var $_owner_id;
    var $_group_id;
    var $_perm_owner;
    var $_perm_group;
    var $_perm_members;
    var $_perm_anon;

    /**
     * Misc display fields we also load from the database for a story:
     */
    var $_username;
    var $_fullname;
    var $_photo;
    var $_email;
    var $_tid;
    var $_topic;
    var $_imageurl;

    /**
     * The original SID of the article, cached incase it's changed:
     */
    var $_originalSid;

    /**
     * The access level.
     */
    var $_access;

    /**
     * Array of images uploaded for the story.
     */
    var $_storyImages;

    /**
     * Magic array used for cheating when loading/saving stories from/to db.
     *
     * List of database field names (which are translated into member variables
     * by prepending _ to the value) as pointers to whether or not they are used
     * to save data. Everything with a save value of 1 will be saved, those with
     * a save value of 0 will just be loaded.
     *
     * This allows us to automate the loading of story, user and topic from a
     * database result array, and generate saving of a story, from the same
     * magic array.
     */
    var $_dbFields = array
         (
           'sid' => 1,
           'uid' => 1,
           'draft_flag' => 1,
           'date' => 1,
           'title' => 1,
           'page_title' => 1,
           'meta_description' => 1,
           'meta_keywords' => 1,
           'introtext' => 1,
           'bodytext' => 1,
           'text_version' => 1,
           'hits' => 1,
           'numemails' => 1,
           'comments' => 1,
           'trackbacks' => 1,
           'related' => 1,
           'featured' => 1,
           'show_topic_icon' => 1,
           'commentcode' => 1,
           'comment_expire' => 1,
           'trackbackcode' => 1,
           'statuscode' => 1,
           'expire' => 1,
           'postmode' => 1,
           'advanced_editor_mode' => 1,
           'frontpage' => 1,
           'cache_time' => 1,
           'owner_id' => 1,
           'group_id' => 1,
           'perm_owner' => 1,
           'perm_group' => 1,
           'perm_members' => 1,
           'perm_anon' => 1,
           'imageurl' => 0,
           'tid' => 0,
           'topic' => 0,
           'access' => 0,
           'photo' => 0,
           'email' => 0
         );
    /**
     * Magic array used for loading basic data from posted form. Of form:
     * postfield -> numeric, target, used with COM_applyFilter. Some fields
     * have exceptions applied
     */
    var $_postFields = array
         (
           'uid' => array
              (
                STORY_AL_NUMERIC,
                '_uid'
              ),
           //'tid' => array
           //   (
           //     STORY_AL_ALPHANUM,
           //     '_tid'
           //   ),
           'page_title' => array
              (
                STORY_AL_ANYTHING,
                '_page_title'
              ),
           'meta_description' => array
              (
                STORY_AL_ANYTHING,
                '_meta_description'
              ),
           'meta_keywords' => array
              (
                STORY_AL_ANYTHING,
                '_meta_keywords'
              ),
           'show_topic_icon' => array
              (
                STORY_AL_CHECKBOX,
                '_show_topic_icon'
              ),
           'draft_flag' => array
              (
                STORY_AL_CHECKBOX,
                '_draft_flag'
              ),
           'statuscode' => array
              (
                STORY_AL_NUMERIC,
                '_statuscode'
              ),
           'featured' => array
              (
                STORY_AL_NUMERIC,
                '_featured'
              ),
           'frontpage' => array
              (
                STORY_AL_NUMERIC,
                '_frontpage'
              ),
           'commentcode' => array
              (
                STORY_AL_NUMERIC,
                '_commentcode'
              ),
           'trackbackcode' => array
              (
                STORY_AL_NUMERIC,
                '_trackbackcode'
              ),
           'postmode' => array
              (
                STORY_AL_ALPHANUM,
                '_postmode'
              ),
           'story_hits' => array
              (
                STORY_AL_NUMERIC,
                '_hits'
              ),
           'story_comments' => array
              (
                STORY_AL_NUMERIC,
                '_comments'
              ),
           'story_emails' => array
              (
                STORY_AL_NUMERIC,
                '_numemails'
              ),
           'story_trackbacks' => array
              (
                STORY_AL_NUMERIC,
                '_trackbacks'
              ),
           'cache_time' => array
              (
                STORY_AL_NUMERIC,
                '_cache_time'
              ),
           'owner_id' => array
              (
                STORY_AL_NUMERIC,
                '_owner_id'
              ),
           'group_id' => array
              (
                STORY_AL_NUMERIC,
                '_group_id'
              ),
           'type' => array
              (
                STORY_AL_ALPHANUM,
                'type'
              ),
           'hits' => array
              (
                STORY_AL_NUMERIC,
                '_hits'
              ),
           'comments' => array
              (
                STORY_AL_NUMERIC,
                '_comments'
              ),
           'trackbacks' => array
              (
                STORY_AL_NUMERIC,
                '_trackbacks'
              )
         );

    //End Private

    // End Variables.
    /**************************************************************************/

    /**************************************************************************/
    // Public Methods:
    /**
     * Constructor, creates a story, taking a (geeklog) database object.
     * @param $mode   string    Story class mode, either 'admin' or 'submission'
     */
    function Story($mode = 'admin')
    {
        $this->mode = $mode;
    }

    /**
     * Check to see if there is any content in the story, for
     * bothering to preview testing really.
     *
     * @return boolean trim(title+intro+body) != ''
     */
    function hasContent()
    {
        if (trim($this->_title) != '') {
            return true;
        }
        if (trim($this->_introtext) != '') {
            return true;
        }
        if (trim($this->_bodytext) != '') {
            return true;
        }

        return false;
    }

    /**
     * Loads a story object from an array (that's come back from the db..)
     *
     * Used from loadFromDatabase, and used on it's own from story list
     * pages.
     * @param  $story  array   Story array from db
     * @return nowt?
     */
    function loadFromArray($story)
    {
        /* Use the magic cheat array to quickly reload the whole story
         * from the database result array, doing the quick stripslashes.
         */
        reset($this->_dbFields);

        while (list($fieldname,$save) = each($this->_dbFields)) {
            $varname = '_' . $fieldname;

            if (array_key_exists($fieldname, $story)) {
                // This is meaningless, and have a negative effect. (bug #0001655)
                // $this->{$varname} = stripslashes($story[$fieldname]);
                $this->{$varname} = $story[$fieldname];
            }
        }

        if (array_key_exists('username', $story)) {
            $this->_username = $story['username'];
        }
        if (array_key_exists('fullname', $story)) {
            $this->_fullname = $story['fullname'];
        }

        // Overwrite the date with the timestamp.
        $this->_date = $story['unixdate'];

        if (!empty($story['expireunix'])) {
            $this->_expire = $story['expireunix'];
        } else {
            $this->_expire = 0;
        }

        if (!empty($story['cmt_expire_unix'])) {
            $this->_comment_expire = $story['cmt_expire_unix'];
        } else {
            $this->_comment_expire = 0;
        }

        // Store the original SID
        $this->_originalSid = $this->_sid;
    }

    /**
     * Load a Story object from the sid specified, returning a status result.
     * The result will either be a permission denied message, invalid SID
     * message, or a loaded ok message. If it's loaded ok, then we've got all
     * the exciting gubbins here.
     *
     * Only used from story admin and submit.php!
     *
     * @param $sid  string  Story Identifier, valid geeklog story id from the db.
     * @return Integer from a constant.
     */
    function loadFromDatabase($sid, $mode = 'edit')
    {
        global $_TABLES, $_CONF, $_USER, $topic;

        $sid = DB_escapeString(COM_applyFilter($sid));

        $sql = array();
        if (!empty($sid) && (($mode == 'edit') || ($mode == 'view') || ($mode == 'clone'))) {
            if (empty($topic)) {
                $topic_sql = ' AND ta.tdefault = 1';
            } else {
                $topic_sql = " AND ta.tid = '{$topic}'";
            }

            /* Original
            $sql['mysql'] = "SELECT STRAIGHT_JOIN s.*, UNIX_TIMESTAMP(s.date) AS unixdate, UNIX_TIMESTAMP(s.expire) AS expireunix, UNIX_TIMESTAMP(s.comment_expire) AS cmt_expire_unix, "
                . "u.username, u.fullname, u.photo, u.email, t.topic, t.imageurl " . "FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t " . "WHERE (s.uid = u.uid) AND (s.tid = t.tid) AND (sid = '$sid')";
            */
            $sql['mysql'] = "SELECT s.*, UNIX_TIMESTAMP(s.date) AS unixdate, UNIX_TIMESTAMP(s.expire) AS expireunix, UNIX_TIMESTAMP(s.comment_expire) AS cmt_expire_unix, u.username, u.fullname, u.photo, u.email, t.tid, t.topic, t.imageurl
                FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t, {$_TABLES['topic_assignments']} AS ta
                WHERE ta.type = 'article' AND ta.id = sid {$topic_sql} AND (s.uid = u.uid) AND (ta.tid = t.tid) AND (sid = '$sid')";

            $sql['mssql'] = "SELECT s.sid, s.uid, s.draft_flag, s.tid, s.date, s.title, CAST(s.introtext AS text) AS introtext, CAST(s.bodytext AS text) AS bodytext, s.text_version, s.hits, s.numemails, s.comments, s.trackbacks, s.related, s.featured, s.show_topic_icon, s.commentcode, s.trackbackcode, s.statuscode, s.expire, s.postmode, s.frontpage, s.cache_time, s.owner_id, s.group_id, s.perm_owner, s.perm_group, s.perm_members, s.perm_anon, s.advanced_editor_mode, UNIX_TIMESTAMP(s.date) AS unixdate, UNIX_TIMESTAMP(s.expire) AS expireunix, UNIX_TIMESTAMP(s.comment_expire) AS cmt_expire_unix, u.username, u.fullname, u.photo, u.email, t.tid, t.topic, t.imageurl
                FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t, {$_TABLES['topic_assignments']} AS ta
                WHERE ta.type = 'article' AND ta.id = sid AND ta.tdefault = 1 AND (s.uid = u.uid) AND (ta.tid = t.tid) AND (sid = '$sid')";

            $sql['pgsql'] = "SELECT s.*, UNIX_TIMESTAMP(s.date) AS unixdate, UNIX_TIMESTAMP(s.expire) as expireunix, UNIX_TIMESTAMP(s.comment_expire) as cmt_expire_unix, u.username, u.fullname, u.photo, u.email, t.tid, t.topic, t.imageurl
                FROM {$_TABLES['stories']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t, {$_TABLES['topic_assignments']} AS ta
                WHERE ta.type = 'article' AND ta.id = sid AND ta.tdefault = 1 AND (s.uid = u.uid) AND (ta.tid = t.tid) AND (sid = '$sid')";
        } elseif (!empty($sid) && ($mode == 'editsubmission')) {
            /* Original
            $sql['mysql'] = 'SELECT STRAIGHT_JOIN s.*, UNIX_TIMESTAMP(s.date) AS unixdate, '
                . 'u.username, u.fullname, u.photo, u.email, t.topic, t.imageurl, t.group_id, ' . 't.perm_owner, t.perm_group, t.perm_members, t.perm_anon ' . 'FROM ' . $_TABLES['storysubmission'] . ' AS s, ' . $_TABLES['users'] . ' AS u, ' . $_TABLES['topics'] . ' AS t WHERE (s.uid = u.uid) AND' . ' (s.tid = t.tid) AND (sid = \'' . $sid . '\')';
            $sql['mssql'] = 'SELECT STRAIGHT_JOIN s.*, UNIX_TIMESTAMP(s.date) AS unixdate, '
                . 'u.username, u.fullname, u.photo, u.email, t.topic, t.imageurl, t.group_id, ' . 't.perm_owner, t.perm_group, t.perm_members, t.perm_anon ' . 'FROM ' . $_TABLES['storysubmission'] . ' AS s, ' . $_TABLES['users'] . ' AS u, ' . $_TABLES['topics'] . ' AS t WHERE (s.uid = u.uid) AND' . ' (s.tid = t.tid) AND (sid = \'' . $sid . '\')';
            $sql['pgsql'] = 'SELECT  s.*, UNIX_TIMESTAMP(s.date) AS unixdate, '
                . 'u.username, u.fullname, u.photo, u.email, t.topic, t.imageurl, t.group_id, ' . 't.perm_owner, t.perm_group, t.perm_members, t.perm_anon ' . 'FROM ' . $_TABLES['storysubmission'] . ' AS s, ' . $_TABLES['users'] . ' AS u, ' . $_TABLES['topics'] . ' AS t WHERE (s.uid = u.uid) AND' . ' (s.tid = t.tid) AND (sid = \'' . $sid . '\')';
            */
            $sql['mysql'] = "SELECT s.*, UNIX_TIMESTAMP(s.date) AS unixdate, u.username, u.fullname, u.photo, u.email, t.tid, t.topic, t.imageurl, t.group_id, t.perm_owner, t.perm_group, t.perm_members, t.perm_anon
                FROM {$_TABLES['storysubmission']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t, {$_TABLES['topic_assignments']} AS ta
                WHERE (s.uid = u.uid) AND  (ta.tid = t.tid) AND (sid = '$sid')
                AND ta.type = 'article' AND ta.id = sid AND ta.tdefault = 1";

            $sql['mssql'] = $sql['mysql'];

            $sql['pgsql'] = "SELECT  s.*, UNIX_TIMESTAMP(s.date) AS unixdate, u.username, u.fullname, u.photo, u.email, t.tid, t.topic, t.imageurl, t.group_id, t.perm_owner, t.perm_group, t.perm_members, t.perm_anon
                FROM {$_TABLES['storysubmission']} AS s, {$_TABLES['users']} AS u, {$_TABLES['topics']} AS t, {$_TABLES['topic_assignments']} AS ta
                WHERE (s.uid = u.uid) AND  (ta.tid = t.tid) AND (sid = '$sid')
                AND ta.type = 'article' AND ta.id = sid AND ta.tdefault = 1";
        } elseif ($mode == 'edit') {
            $this->_sid = COM_makesid();
            $this->_old_sid = $this->_sid;

            if (isset($_CONF['draft_flag'])) {
                $this->_draft_flag = $_CONF['draft_flag'];
            } else {
                $this->_draft_flag = 0;
            }

            if (isset($_CONF['show_topic_icon'])) {
                $this->_show_topic_icon = $_CONF['show_topic_icon'];
            } else {
                $this->_show_topic_icon = 1;
            }

            if (isset($_CONF['default_cache_time_article'])) {
                $this->_cache_time = $_CONF['default_cache_time_article'];
            } else {
                $this->_cache_time = 0;
            }

            if (COM_isAnonUser()) {
                $this->_uid = 1;
            } else {
                $this->_uid = $_USER['uid'];
            }
            $this->_date = time();
            $this->_expire = time();
            if ($_CONF['article_comment_close_enabled']) {
                $this->_comment_expire = time() +
                    ($_CONF['article_comment_close_days'] * 86400);
            } else {
                $this->_comment_expire = 0;
            }
            $this->_commentcode = $_CONF['comment_code'];
            $this->_trackbackcode = $_CONF['trackback_code'];
            $this->_title = '';
            $this->_page_title = '';
            $this->_meta_description = '';
            $this->_meta_keywords = '';
            $this->_introtext = '';
            $this->_bodytext = '';

            if (isset($_CONF['frontpage'])) {
                $this->_frontpage = $_CONF['frontpage'];
            } else {
                $this->_frontpage = 1;
            }

            $this->_text_version = GLTEXT_LATEST_VERSION;
            $this->_hits = 0;
            $this->_comments = 0;
            $this->_trackbacks = 0;
            $this->_numemails = 0;

            if (($_CONF['advanced_editor'] && $_USER['advanced_editor']) &&
                    ($_CONF['postmode'] !== 'plaintext') &&
                    ($_CONF['postmode'] !== 'wikitext')) {
                $this->_advanced_editor_mode = 1;
                $this->_postmode = 'adveditor';
            } else {
                $this->_postmode = $_CONF['postmode'];
                $this->_advanced_editor_mode = 0;
            }

            $this->_statuscode = 0;
            $this->_featured = 0;
            $this->_cache_time = $_CONF['default_cache_time_article'];
            if (COM_isAnonUser()) {
                $this->_owner_id = 1;
            } else {
                $this->_owner_id = $_USER['uid'];
            }

            if (isset($_GROUPS['Story Admin'])) {
                $this->_group_id = $_GROUPS['Story Admin'];
            } else {
                $this->_group_id = SEC_getFeatureGroup('story.edit');
            }

            $array = array();
            SEC_setDefaultPermissions($array, $_CONF['default_permissions_story']);
            $this->_perm_owner = $array['perm_owner'];
            $this->_perm_group = $array['perm_group'];
            $this->_perm_anon = $array['perm_anon'];
            $this->_perm_members = $array['perm_members'];
        } else {
            $this->loadFromArgsArray($_POST);
        }

        /* if we have SQL, load from it */
        if (!empty($sql)) {
            $result = DB_query($sql);

            if ($result) {
                $story = DB_fetchArray($result, false);
                if ($story == null) {
                    return STORY_INVALID_SID;
                }
                $this->loadFromArray($story);

                /**
                * The above SQL also got the story owner's username etc. from
                * the DB. If the user doing the cloning is different from the
                * original author, we need to fix those here.
                */
                if (($mode == 'clone') && ($this->_uid != $_USER['uid'])) {
                    $this->_uid = $_USER['uid'];
                    $story['owner_id'] = $this->_uid;
                    $uresult = DB_query("SELECT username, fullname, photo, email FROM {$_TABLES['users']} WHERE uid = {$_USER['uid']}");
                    list($this->_username, $this->_fullname, $this->_photo, $this->_email) = DB_fetchArray($uresult);
                }

                if (!isset($story['owner_id'])) {
                    $story['owner_id'] = 1;
                }
                $access = SEC_hasAccess($story['owner_id'], $story['group_id'],
                            $story['perm_owner'], $story['perm_group'],
                            $story['perm_members'], $story['perm_anon']);

                //$this->_access = min($access, SEC_hasTopicAccess($this->_tid));
                //$this->_access = min($access, TOPIC_hasMultiTopicAccess('article', $sid));
                if ($mode != 'view') {
                    // When editing an article they need access to all topics article is assigned to plus edit access to article itself
                    $this->_access = min($access, TOPIC_hasMultiTopicAccess('article', $sid));
                } else {
                    // When viewing a article we only care about if it has access to the current topic and article
                    $this->_access = min($access, TOPIC_hasMultiTopicAccess('article', $sid, $topic));
                }

                if ($this->_access == 0) {
                    return STORY_PERMISSION_DENIED;
                } elseif ($this->_access == 2 && $mode != 'view') {
                    return STORY_EDIT_DENIED;
                } elseif ((($this->_access == 2) && ($mode == 'view')) && (($this->_draft_flag == 1) || ($this->_date > time()))) {
                        return STORY_INVALID_SID;
                }
            } else {
                return STORY_INVALID_SID;
            }
        }

        if ($mode == 'editsubmission') {
            if (isset($_CONF['draft_flag'])) {
                $this->_draft_flag = $_CONF['draft_flag'];
            } else {
                $this->_draft_flag = 1;
            }

            if (isset($_CONF['show_topic_icon'])) {
                $this->_show_topic_icon = $_CONF['show_topic_icon'];
            } else {
                $this->_show_topic_icon = 1;
            }

            $this->_commentcode = $_CONF['comment_code'];
            $this->_trackbackcode = $_CONF['trackback_code'];
            $this->_featured = 0;
            $this->_cache_time = $_CONF['default_cache_time_article'];
            $this->_expire = time();
            if ($_CONF['article_comment_close_enabled']) {
                $this->_comment_expire = time() +
                    ($_CONF['article_comment_close_days'] * 86400);
            } else {
                $this->_comment_expire = 0;
            }

            if (isset($_CONF['frontpage'])) {
                $this->_frontpage = $_CONF['frontpage'];
            } else {
                $this->_frontpage = 1;
            }

            $this->_text_version = GLTEXT_LATEST_VERSION;
            $this->_comments = 0;
            $this->_trackbacks = 0;
            $this->_numemails = 0;
            $this->_statuscode = 0;
            $this->_owner_id = $this->_uid;

        } elseif ($mode == 'clone') {

            // new story, new sid ...
            $this->_sid = COM_makesid();
            $this->_old_sid = $this->_sid;

            // assign ownership to current user
            if (COM_isAnonUser()) {
                $this->_uid = 1;
            } else {
                $this->_uid = $_USER['uid'];
            }
            $this->_owner_id = $this->_uid;

            // use current date + time
            $this->_date = time();
            $this->_expire = time();

            // if the original story uses comment expire, update the time
            if ($this->_comment_expire != 0) {
                $this->_comment_expire = time() +
                    ($_CONF['article_comment_close_days'] * 86400);
            }

            // reset counters
            $this->_hits = 0;
            $this->_comments = 0;
            $this->_trackbacks = 0;
            $this->_numemails = 0;
        }

        $this->_sanitizeData();

        return STORY_LOADED_OK;
    }

    /**
     * Saves the story in it's final state to the database.
     *
     * Handles all the SID magic etc.
     * @return Integer status result from a constant list.
     */
    function saveToDatabase()
    {
        global $_TABLES,$_DB_dbms;


        $tids = TOPIC_getTopicIdsForObject('topic');
        $archive_tid = DB_getItem($_TABLES['topics'], 'tid', 'archive_flag=1');
        if (!empty($tids) && !empty($archive_tid)) {
            if (in_array($archive_tid, $tids)) {
                $this->_featured = 0;
                $this->_frontpage = 0;
                $this->_statuscode = STORY_ARCHIVE_ON_EXPIRE;
            }
        }

        /* if a featured, non-draft, that goes live straight away, unfeature
         * other stories in same topic:
         */
        if ($this->_featured == '1') {
            // there can only be one non-draft featured story
            if ($this->_draft_flag == 0 AND $this->_date <= time()) {
                if ($this->_frontpage == 1) {
                    // un-feature any featured frontpage story
                    DB_query("UPDATE {$_TABLES['stories']} SET featured = 0 WHERE featured = 1 AND draft_flag = 0 AND frontpage = 1 AND date <= NOW()");
                }

                // un-feature any featured story in the same topic
                //DB_query("UPDATE {$_TABLES['stories']} SET featured = 0 WHERE featured = 1 AND draft_flag = 0 AND tid = '{$this->_tid}' AND date <= NOW()");
                $tids = TOPIC_getTopicIdsForObject('topic');
                if (!empty($tids)) {
                    DB_query("UPDATE {$_TABLES['stories']} s, {$_TABLES['topic_assignments']} ta SET s.featured = 0 WHERE s.featured = 1 AND s.draft_flag = 0 AND (ta.tid IN ('" . implode( "','", $tids ) . "')) AND ta.type = 'article' AND ta.id = s.sid AND s.date <= NOW()");
                }
            }
        }

        $oldArticleExists = false;
        $currentSidExists = false;

        /* Fix up old sid => new sid stuff */
        $checksid = DB_escapeString($this->_originalSid); // needed below

        if ($this->_sid != $this->_originalSid) {
            /* The sid has changed. Load from request will have
             * ensured that if the new sid exists an error has
             * been thrown, but we need to know if the old sid
             * actually existed (as opposed to being a generated
             * sid that was then thrown away) to reduce the sheer
             * number of SQL queries we do.
             */
            $newsid = DB_escapeString($this->_sid);

            $sql = "SELECT 1 FROM {$_TABLES['stories']} WHERE sid='{$checksid}'";
            $result = DB_query($sql);

            if ($result && (DB_numRows($result) > 0)) {
                $oldArticleExists = true;
            }

            if ($oldArticleExists) {
                /* Move Comments */
                $sql = "UPDATE {$_TABLES['comments']} SET sid='$newsid' WHERE type='article' AND sid='$checksid'";
                DB_query($sql);

                /* Move Images */
                $sql = "UPDATE {$_TABLES['article_images']} SET ai_sid = '{$newsid}' WHERE ai_sid = '{$checksid}'";
                DB_query($sql);

                /* Move trackbacks */
                $sql = "UPDATE {$_TABLES['trackback']} SET sid='{$newsid}' WHERE sid='{$checksid}' AND type='article'";
                DB_query($sql);
            }
        }

        /* Acquire Comment Count */
        $sql = "SELECT COUNT(1) FROM {$_TABLES['comments']} WHERE type='article' AND sid='{$this->_sid}'";
        $result = DB_query($sql);

        if ($result && (DB_numRows($result) == 1)) {
            $array = DB_fetchArray($result);
            $this->_comments = $array[0];
        } else {
            $this->_comments = 0;
        }

        /* Format dates for storage: */
        /*
         * Doing this here would use the webserver's timezone, but we need
         * to use the DB server's timezone so that ye olde timezone hack
         * still works. See use of FROM_UNIXTIME in the SQL below.
         *
         * $this->_date = date('Y-m-d H:i:s', $this->_date);
         * $this->_expire = date('Y-m-d H:i:s', $this->_expire);
         *
         */

        // Get the related URLs
        $this->_related = implode("\n", STORY_extractLinks($this->DisplayElements('introtext') . ' ' . $this->DisplayElements('bodytext')));
        $fields='';
        $values = '';
        reset($this->_dbFields);

        $this->_text_version = GLTEXT_LATEST_VERSION;

        // Apply HTML filter to the text just before save
        // with the permissions of current editor
        $this->_introtext = GLText::applyHTMLFilter(
                $this->_introtext,
                $this->_postmode,
                'story.edit',
                $this->_text_version);
        $this->_bodytext = GLText::applyHTMLFilter(
                $this->_bodytext,
                $this->_postmode,
                'story.edit',
                $this->_text_version);

        /* This uses the database field array to generate a SQL Statement. This
         * means that when adding new fields to save and load, all we need to do
         * is add the field name to the array, and the code will magically cope.
         */
        while (list($fieldname, $save) = each($this->_dbFields)) {
            if ($save === 1) {
                $varname = '_' . $fieldname;
                $fields .= $fieldname . ', ';
                if (($fieldname == 'date') || ($fieldname == 'expire') ||
                        ($fieldname == 'comment_expire')) {
                    // let the DB server do this conversion (cf. timezone hack)
                    $values .= 'FROM_UNIXTIME(' . $this->{$varname} . '), ';
                } else {
                    if ($this->{$varname} === '')
                    {
                        $values.="'', ";
                    }
                    else
                    {
                        if(is_numeric($this->{$varname}))
                        {
                            $values .= DB_escapeString($this->{$varname}).', ';
                        }
                        else
                        {
                            $values .= '\''. DB_escapeString($this->{$varname}) . '\', ';
                        }
                    }
                }
            }
        }

        $fields = substr($fields, 0, strlen($fields) - 2);
        $values = substr($values, 0, strlen($values) - 2);

        DB_save($_TABLES['stories'],$fields,$values);

        // Save Topics selected
        TOPIC_saveTopicSelectionControl('article', $this->_sid);

        if ($oldArticleExists) {
            /* Clean up the old story */
            DB_delete($_TABLES['stories'], 'sid', $checksid);

            // Delete Topic Assignments for this old article id since we just created new ones
            TOPIC_deleteTopicAssignments('article', $checksid);
        }

        if ($this->type == 'submission') {
            /* there might be a submission, clean it up */
            DB_delete($_TABLES['storysubmission'], 'sid', $checksid);
        }

        return STORY_SAVED;
    }

    /**
     * Loads a story from the post data. This is the most exciting function in
     * the whole entire world. First it'll clean up that horrible Magic Quotes
     * crap. Then it'll do all Geeklog's funky security stuff, anti XSS, anti
     * SQL Injection. Yay.
     */
    function loadFromArgsArray(&$array)
    {
        global $_TABLES, $_CONF;

        /* magic_quotes_gpc cleanup routine now in submitstory() in
         * /public_html/admin/story.php
         */

        $retval = STORY_LOADED_OK; // default to success


        /* Load the trivial stuff: */
        $this->_loadBasics($array);

        // override the GLText version to the latest version
        $this->_text_version = GLTEXT_LATEST_VERSION;

        /* Check to see if we have permission to edit this sid, and that this
         * sid is not a duplicate or anything horrible like that. ewww.
         */
        $sql
        = 'SELECT owner_id, group_id, perm_owner, perm_group, perm_members, perm_anon ' . ' FROM ' . $_TABLES['stories']
            . ' WHERE sid=\'' . $this->_sid . '\'';
        $result = DB_query($sql);

        if ($result && (DB_numRows($result) > 0)) {
            /* Sid exists! Is it our article? */
            if ($this->_sid != $this->_originalSid) {
                // for story preview: don't abort
                $retval = STORY_DUPLICATE_SID;
            }

            $article = DB_fetchArray($result);
            /* Check Security */
            if (SEC_hasAccess($article['owner_id'], $article['group_id'],
                    $article['perm_owner'], $article['perm_group'],
                    $article['perm_members'], $article['perm_anon']) < 3) {
                return STORY_EXISTING_NO_EDIT_PERMISSION;
            }
        }

        $access = SEC_hasAccess($this->_owner_id, $this->_group_id, $this->_perm_owner, $this->_perm_group,
                                    $this->_perm_members, $this->_perm_anon);

        //if (($access < 3) || !SEC_hasTopicAccess($this->_tid) || !SEC_inGroup($this->_group_id)) {
        if (($access < 3) || !TOPIC_hasMultiTopicAccess('topic') || !SEC_inGroup($this->_group_id)) {
            return STORY_NO_ACCESS_PARAMS;
        }

        /* Load up the topic name and icon */
        $topic = DB_query("SELECT tid, topic, imageurl FROM {$_TABLES['topics']} WHERE tid='" . TOPIC_getTopicDefault('topic') . "'");
        $topic = DB_fetchArray($topic);
        $this->_tid = $topic['tid'];
        $this->_topic = $topic['topic'];
        $this->_imageurl = $topic['imageurl'];

        /* Load the title, page title */
        $this->_title      = $this->_applyTitleFilter($array['title']);
        $this->_page_title = $this->_applyTitleFilter($array['page_title']);

        // fix for bug in advanced editor
        if (in_array($array['postmode'], array('html', 'adveditor', 'wikitext'))) {
            if ($_CONF['advanced_editor'] && ($array['bodytext'] == '<br' . XHTML . '>')) {
                $array['bodytext'] = '';
            }
        }

        /* Load the introtext, bodytext */
        $this->_introtext = $this->_applyTextFilter($array['introtext'], $array['postmode']);
        $this->_bodytext  = $this->_applyTextFilter($array['bodytext'],  $array['postmode']);

        $this->_advanced_editor_mode = 0;
        if (in_array($array['postmode'], array('html', 'adveditor', 'wikitext'))) {
            if ($this->_postmode == 'adveditor') {
                $this->_advanced_editor_mode = 1;
                $this->_postmode = 'html';
            }
        }

        if (empty($this->_title) || empty($this->_introtext)) {
            return STORY_EMPTY_REQUIRED_FIELDS;
        }

        $this->_sanitizeData();

        return $retval;
    }

    /**
     * Sets up basic data for a new user submission story
     */
    function initSubmission()
    {
        global $_USER, $_CONF, $_TABLES, $topic;

        if (COM_isAnonUser()) {
            $this->_uid = 1;
        } else {
            $this->_uid = $_USER['uid'];
        }

        // initialize the GLText version to the latest version
        $this->_text_version = GLTEXT_LATEST_VERSION;

        $this->_postmode = $_CONF['postmode'];

        // If a topic has been specified, use it, if permitted
        // otherwise, fall back to the default permitted topic.
        // if we still don't have one...

        // Have we specified a permitted topic?
        if (!empty($topic)) {
            $allowed = DB_getItem($_TABLES['topics'], 'tid', "tid = '" . DB_escapeString($topic) . "'" . COM_getTopicSql('AND'));

            if ($allowed != $topic) {
                $topic = '';
            }
        }

        // Do we now not have a topic?
        if (empty($topic)) {
            // Get default permitted:
            $topic = DB_getItem($_TABLES['topics'], 'tid', 'is_default = 1' . COM_getPermSQL('AND'));
        }

        // Use what we have:
        $this->_tid = $topic;
        $this->_date = time();
    }

    /**
     * Loads a submitted story from postdata
     */
    function loadSubmission()
    {
        global $_CONF;

        $array = $_POST;

        $this->_expire = time();
        $this->_date = time();
        if ($_CONF['article_comment_close_enabled']) {
            $this->_comment_expire = time() +
                ($_CONF['article_comment_close_days'] * 86400);
        } else {
            $this->_comment_expire = 0;
        }

        // Handle Magic GPC Garbage:
        while (list($key, $value) = each($array))
        {
            $array[$key] = COM_stripslashes($value);
        }

        // initialize the GLText version to the latest version
        $this->_text_version = GLTEXT_LATEST_VERSION;

        $this->_postmode = COM_applyFilter($array['postmode']);
        $this->_sid = COM_applyFilter($array['sid']);
        $this->_uid = COM_applyFilter($array['uid'], true);
        if ($this->_uid < 1) {
            $this->_uid = 1;
        }
        $this->_unixdate = COM_applyFilter($array['date'], true);

        if (!isset($array['bodytext'])) {
            $array['bodytext'] = '';
        }

        if (!isset($array['page_title'])) {
            $array['page_title'] = '';
        }

        /* Load the title, page title */
        $this->_title      = $this->_applyTitleFilter($array['title']);
        $this->_page_title = $this->_applyTitleFilter($array['page_title']);

        // fix for bug in advanced editor
        if (in_array($array['postmode'], array('html', 'adveditor'))) {
            if ($_CONF['advanced_editor'] && ($array['bodytext'] == '<br' . XHTML . '>')) {
                $array['bodytext'] = '';
            }
        }

        /* Load the introtext, bodytext */
        $this->_introtext = $this->_applyTextFilter($array['introtext'], $array['postmode']);
        $this->_bodytext  = $this->_applyTextFilter($array['bodytext'],  $array['postmode']);

        $this->_advanced_editor_mode = 0;
        if (in_array($array['postmode'], array('html', 'adveditor'))) {
            if ($this->_postmode == 'adveditor') {
                $this->_advanced_editor_mode = 1;
                $this->_postmode = 'html';
            }
        }

        if (!TOPIC_checkTopicSelectionControl()) {
            return STORY_EMPTY_REQUIRED_FIELDS;
        }

        if (empty($this->_title) || empty($this->_introtext)) {
            return STORY_EMPTY_REQUIRED_FIELDS;
        }

        return STORY_LOADED_OK;
    }

    /**
     * Returns a story formatted for spam check:
     *
     * @return  string Story formatted for spam check.
     */
    function GetSpamCheckFormat()
    {
        return "<h1>{$this->_title}</h1><p>{$this->_introtext}</p><p>{$this->_bodytext}</p>";
    }

    /**
     * Saves a story submission.
     *
     * @return  integer result code explaining behaviour.
     */
    function saveSubmission()
    {
        global $_USER, $_CONF, $_TABLES;
        $this->_sid = COM_makeSid();

        if (COM_isAnonUser()) {
            $this->_uid = 1;
        } else {
            $this->_uid = $_USER['uid'];
        }


        // Remove any autotags the user doesn't have permission to use
        $introtext = PLG_replaceTags($this->_introtext, '', true);
        $bodytext = PLG_replaceTags($this->_bodytext, '', true);

        if (!TOPIC_hasMultiTopicAccess('topic')) {
            // user doesn't have access to one or more topics - bail
            return STORY_NO_ACCESS_TOPIC;
        }


        if (($_CONF['storysubmission'] == 1) && !SEC_hasRights('story.submit')) {
            $sid = DB_escapeString($this->_sid);
            $title = DB_escapeString($this->_title);

            $introtext = DB_escapeString($introtext);
            $bodytext = DB_escapeString($bodytext);
            $postmode = DB_escapeString($this->_postmode);
            DB_save($_TABLES['storysubmission'], 'sid,uid,title,introtext,bodytext,date,postmode,text_version',
                        "$sid,{$this->_uid},'$title'," .
                        "'$introtext','$bodytext',NOW(),'$postmode','{$this->_text_version}'");

            // Save Topics selected
            TOPIC_saveTopicSelectionControl('article', $sid);

            return STORY_SAVED_SUBMISSION;
        } else {
            // post this story directly. First establish the necessary missing data.
            $this->_sanitizeData();

            if (!isset($_CONF['show_topic_icon'])) {
                $_CONF['show_topic_icon'] = 1;
            }
/*
            if (DB_getItem($_TABLES['topics'], 'archive_flag', "tid = '{$tmptid}'") == 1) { // A bug using undefined variable $tmptid
                $this->_frontpage = 0;
            } elseif (isset($_CONF['frontpage'])) {
                $this->_frontpage = $_CONF['frontpage'];
            } else {
                $this->_frontpage = 1;
            }

            $this->_oldsid = $this->_sid; // dead code
*/
            $this->_date = mktime();
            $this->_featured = 0;
            $this->_commentcode = $_CONF['comment_code'];
            $this->_trackbackcode = $_CONF['trackback_code'];
            $this->_statuscode = 0;
            $this->_show_topic_icon = $_CONF['show_topic_icon'];
            $this->_cache_time = $_CONF['default_cache_time_article'];
            if (COM_isAnonUser()) {
                $this->_owner_id = 1;
            } else {
                $this->_owner_id = $_USER['uid'];
            }

            /*
            $this->_group_id = $T['group_id'];
            $this->_perm_owner = $T['perm_owner'];
            $this->_perm_group = $T['perm_group'];
            $this->_perm_members = $T['perm_members'];
            $this->_perm_anon = $T['perm_anon'];
            */

            // Save Topics selected
            TOPIC_saveTopicSelectionControl('article', $this->_sid);

            $sql = "SELECT group_id,perm_owner,perm_group,perm_members,perm_anon,archive_flag "
                 . "FROM {$_TABLES['topics']} t, {$_TABLES['topic_assignments']} ta "
                 . "WHERE ta.type = 'article' AND ta.id = '{$this->_sid}' "
                 . "AND ta.tdefault = 1 AND ta.tid = t.tid";
            $result = DB_query($sql);
            $A = DB_fetchArray($result);
            if ($A['archive_flag'] == 1) {
                $this->_frontpage = 0;
            } elseif (isset($_CONF['frontpage'])) {
                $this->_frontpage = $_CONF['frontpage'];
            } else {
                $this->_frontpage = 1;
            }
            $this->_group_id     = $A['group_id'];
            $this->_perm_owner   = $A['perm_owner'];
            $this->_perm_group   = $A['perm_group'];
            $this->_perm_members = $A['perm_members'];
            $this->_perm_anon    = $A['perm_anon'];

            $this->saveToDatabase();

            PLG_itemSaved($this->_sid, 'article');
            COM_rdfUpToDateCheck('article');
            COM_rdfUpToDateCheck('comment');
            STORY_updateLastArticlePublished();

            return STORY_SAVED;
        }
    }

    /**
     * Replaces all special syntax tags in intro and body with image HTML
     *
     * @param    string      $text  Intro or Body text
     * @return   string      processed text
     */
    function renderImageTags($text)
    {
        global $_CONF, $_TABLES, $LANG24;

        // check if we have a (different) old sid - the article_images table
        // will only be updated later! cf. bug #0001256
        if (! empty($this->_originalSid) &&
                ($this->_sid != $this->_originalSid)) {
            $ai_sid = $this->_originalSid;
        } else {
            $ai_sid = $this->_sid;
        }

        $result = DB_query("SELECT ai_filename FROM {$_TABLES['article_images']} "
                         . "WHERE ai_sid = '{$ai_sid}' ORDER BY ai_img_num");
        $nrows = DB_numRows($result);

        $stdImageLoc = true;
        if (!strstr($_CONF['path_images'], $_CONF['path_html'])) {
            $stdImageLoc = false;
        }

        for ($i = 1; $i <= $nrows; $i++) {
            $A = DB_fetchArray($result);

            $imgpath = '';

            // If we are storing images on a "standard path" i.e. is
            // available to the host web server, then the url to this
            // image is based on the path to images, site url, articles
            // folder and it's filename.
            //
            // Otherwise, we have to use the image handler to load the
            // image from whereever else on the file system we're
            // keeping them:
            if ($stdImageLoc) {
                $imgpath = substr($_CONF['path_images'], strlen($_CONF['path_html']));
                $imgSrc = $_CONF['site_url'] . '/' . $imgpath . 'articles/' . $A['ai_filename'];
            } else {
                $imgSrc = $_CONF['site_url'] . '/getimage.php?mode=articles&amp;image=' . $A['ai_filename'];
            }

            $sizeattributes = COM_getImgSizeAttributes($_CONF['path_images'] . 'articles/' . $A['ai_filename']);

            // Build image tags for each flavour of the image:
            $img_noalign   = '<img ' . $sizeattributes . 'src="'                    . $imgSrc . '" alt=""' . XHTML . '>';
            $img_leftalgn  = '<img ' . $sizeattributes . 'class="floatleft" src="'  . $imgSrc . '" alt=""' . XHTML . '>';
            $img_rightalgn = '<img ' . $sizeattributes . 'class="floatright" src="' . $imgSrc . '" alt=""' . XHTML . '>';

            // Are we keeping unscaled images?
            if ($_CONF['keep_unscaled_image'] == 1) {
                // Yes we are, so, we need to find out what the filename
                // of the original, unscaled image is:
                $lFilename_large = substr_replace($A['ai_filename'], '_original.',
                                        strrpos($A['ai_filename'], '.'), 1);
                $lFilename_large_complete = $_CONF['path_images'] . 'articles/' .
                                                $lFilename_large;

                // We need to map that filename to the right location
                // or the fetch script:
                if ($stdImageLoc) {
                    $lFilename_large_URL = $_CONF['site_url'] . '/' . $imgpath .
                                            'articles/' . $lFilename_large;
                } else {
                    $lFilename_large_URL = $_CONF['site_url'] .
                                            '/getimage.php?mode=show&amp;image=' .
                                            $lFilename_large;
                }

                // And finally, replace the [imageX_mode] tags with the
                // image and its hyperlink (only when the large image
                // actually exists)
                $lLink_url  = '';
                $lLink_attr = '';
                if (file_exists($lFilename_large_complete)) {
                    $lLink_url = $lFilename_large_URL;
                    $lLink_attr = array('title' => $LANG24[57]);
                }
            }

            $norm  = '[image' . $i . ']';
            $left  = '[image' . $i . '_left]';
            $right = '[image' . $i . '_right]';

            if (!empty($lLink_url)) {
                $text = str_replace($norm,  COM_createLink($img_noalign,   $lLink_url, $lLink_attr), $text);
                $text = str_replace($left,  COM_createLink($img_leftalgn,  $lLink_url, $lLink_attr), $text);
                $text = str_replace($right, COM_createLink($img_rightalgn, $lLink_url, $lLink_attr), $text);
            } else {
                // We aren't wrapping our image tags in hyperlinks, so
                // just replace the [imagex_mode] tags with the image:
                $text = str_replace($norm,  $img_noalign,   $text);
                $text = str_replace($left,  $img_leftalgn,  $text);
                $text = str_replace($right, $img_rightalgn, $text);
            }

            // And insert the unscaled mode images:
            if (($_CONF['allow_user_scaling'] == 1) and ($_CONF['keep_unscaled_image'] == 1)) {
                if (file_exists($lFilename_large_complete)) {
                    $imgSrc = $lFilename_large_URL;
                    $sizeattributes = COM_getImgSizeAttributes($lFilename_large_complete);
                }

                $unscalednorm  = '[unscaled' . $i . ']';
                $unscaledleft  = '[unscaled' . $i . '_left]';
                $unscaledright = '[unscaled' . $i . '_right]';

                $text = str_replace($unscalednorm,
                    '<img ' . $sizeattributes . 'src="'               . $imgSrc . '" alt=""' . XHTML . '>', $text);
                $text = str_replace($unscaledleft,
                    '<img ' . $sizeattributes . 'align="left" src="'  . $imgSrc . '" alt=""' . XHTML . '>', $text);
                $text = str_replace($unscaledright,
                    '<img ' . $sizeattributes . 'align="right" src="' . $imgSrc . '" alt=""' . XHTML . '>', $text);
            }
        }

        return $text;
    }

    /**
     * Check if using attached images
     *
     * @return array    containing errors, or empty.
     */
    function checkAttachedImages()
    {
        global $_TABLES, $LANG24;

        $text = $this->_introtext . ' ' . $this->_bodytext;

        // check if we have a (different) old sid - the article_images table
        // will only be updated later! cf. bug #0001256
        if (! empty($this->_originalSid) &&
                ($this->_sid != $this->_originalSid)) {
            $ai_sid = $this->_originalSid;
        } else {
            $ai_sid = $this->_sid;
        }

        $result = DB_query("SELECT ai_filename FROM {$_TABLES['article_images']} "
                         . "WHERE ai_sid = '{$ai_sid}' ORDER BY ai_img_num");
        $nrows = DB_numRows($result);
        $errors = array();
        for ($i = 1; $i <= $nrows; $i++) {
            $A = DB_fetchArray($result);

            // See how many times image $i is used in the fulltext of the article:
            $icount = substr_count($text, '[image'    . $i . ']')
                    + substr_count($text, '[image'    . $i . '_left]')
                    + substr_count($text, '[image'    . $i . '_right]')
                    + substr_count($text, '[unscaled' . $i . ']')
                    + substr_count($text, '[unscaled' . $i . '_left]')
                    + substr_count($text, '[unscaled' . $i . '_right]');

            // If the image we are currently looking at wasn't used, we need
            // to log an error
            if ($icount == 0) {
                // There is an image that wasn't used, create an error
                $errors[] = $LANG24[48] . " #$i, {$A['ai_filename']}, " . $LANG24[53];
            }
        }

        return $errors;
    }

    /**
     * This replaces all article image HTML in intro and body with
     * GL special syntax
     *
     * @param    string      $text  Intro or Body text
     * @return   string      processed text
     *
     */
    function replaceImages($text)
    {
        global $_CONF, $_TABLES, $LANG24;

        $stdImageLoc = true;

        if (!strstr($_CONF['path_images'], $_CONF['path_html'])) {
            $stdImageLoc = false;
        }

        $count = 0;
        /* If we haven't already cached the images for this story, do so */
        if (!is_array($this->_storyImages)) {
            $result= DB_query("SELECT ai_filename FROM {$_TABLES['article_images']} WHERE " .
                              "ai_sid = '{$this->_sid}' ORDER BY ai_img_num");
            $nrows = DB_numRows($result);
            $this->_storyImages = array();

            for ($i = 1; $i <= $nrows; $i++)
            {
                $this->_storyImages[] = DB_fetchArray($result);
            }

            $count = $nrows;
        } else {
            $count = count($this->_storyImages);
        }

        // If the article has any images, remove them back to [image] tags.
        for ($i = 0; $i < $count; $i++) {
            $A = $this->_storyImages[$i];

            $imageX       = '[image' . ($i + 1) . ']';
            $imageX_left  = '[image' . ($i + 1) . '_left]';
            $imageX_right = '[image' . ($i + 1) . '_right]';

            $sizeattributes = COM_getImgSizeAttributes($_CONF['path_images'] . 'articles/' . $A['ai_filename']);

            $lLinkPrefix = '';
            $lLinkSuffix = '';

            if ($_CONF['keep_unscaled_image'] == 1) {
                $lFilename_large = substr_replace($A['ai_filename'],
                '_original.', strrpos($A['ai_filename'], '.'), 1);
                $lFilename_large_complete = $_CONF['path_images'] . 'articles/' . $lFilename_large;

                if ($stdImageLoc) {
                    $imgpath = substr($_CONF['path_images'], strlen($_CONF['path_html']));
                    $lFilename_large_URL = $_CONF['site_url'] . '/' . $imgpath . 'articles/' . $lFilename_large;
                } else {
                    $lFilename_large_URL = $_CONF['site_url'] . '/getimage.php?mode=show&amp;image='
                                           . $lFilename_large;
                }

                if (file_exists($lFilename_large_complete)) {
                    $lLinkPrefix = '<a href="' . $lFilename_large_URL . '" title="' . $LANG24[57] . '">';
                    $lLinkSuffix = '</a>';
                }
            }

            if ($stdImageLoc) {
                $imgpath = substr($_CONF['path_images'], strlen($_CONF['path_html']));
                $imgSrc = $_CONF['site_url'] . '/' . $imgpath . 'articles/' . $A['ai_filename'];
            } else {
                $imgSrc = $_CONF['site_url'] . '/getimage.php?mode=articles&amp;image=' . $A['ai_filename'];
            }

            $norm  = $lLinkPrefix . '<img ' . $sizeattributes . 'src="' . $imgSrc . '" alt=""' . XHTML . '>' . $lLinkSuffix;
            $left  = $lLinkPrefix . '<img ' . $sizeattributes . 'class="floatleft" src="' . $imgSrc . '" alt=""' . XHTML . '>'
                    . $lLinkSuffix;
            $right = $lLinkPrefix . '<img ' . $sizeattributes . 'class="floatright" src="' . $imgSrc . '" alt=""' . XHTML . '>'
                    . $lLinkSuffix;

            $text = str_replace($norm, $imageX, $text);
            $text = str_replace($left, $imageX_left, $text);
            $text = str_replace($right, $imageX_right, $text);

            if (($_CONF['allow_user_scaling'] == 1) and ($_CONF['keep_unscaled_image'] == 1)) {
                $unscaledX = '[unscaled' . ($i + 1) . ']';
                $unscaledX_left = '[unscaled' . ($i + 1) . '_left]';
                $unscaledX_right = '[unscaled' . ($i + 1) . '_right]';

                if (file_exists($lFilename_large_complete)) {
                    $sizeattributes = COM_getImgSizeAttributes($lFilename_large_complete);
                    $norm  = '<img ' . $sizeattributes . 'src="' . $lFilename_large_URL . '" alt=""' . XHTML . '>';
                    $left  = '<img ' . $sizeattributes . 'align="left" src="'  . $lFilename_large_URL . '" alt=""' . XHTML . '>';
                    $right = '<img ' . $sizeattributes . 'align="right" src="' . $lFilename_large_URL . '" alt=""' . XHTML . '>';
                }

                $text = str_replace($norm, $unscaledX, $text);
                $text = str_replace($left, $unscaledX_left, $text);
                $text = str_replace($right, $unscaledX_right, $text);
            }
        }

        return $text;
    }

    /**
     * Return the SID in a clean way
     *
     * @param $fordb    boolean True if we want an 'DB_escapeString' version for the db
     */
    function getSid($fordb = false)
    {
        if ($fordb) {
            return DB_escapeString($this->_sid);
        } else {
            return $this->_sid;
        }
    }

    /**
     * Get the access level
     */
    function getAccess()
    {
        return $this->_access;
    }

    /**
     * Provide access to story elements. For the editor.
     *
     * This is a pseudo-property, implementing a getter for story
     * details as if as an associative array. Personally, I'd
     * rather be able to assign getters and setters to actual
     * properties to mask controlled access to private member
     * variables. But, you get what you get with PHP. So here it
     * is in all its nastiness.
     *
     * @param   string  $item   Item to fetch.
     * @return  mixed   The clean and ready to use (in edit mode) value requested.
     */
    function EditElements($item = 'title')
    {
        global $_CONF;
        switch (strtolower($item))
        {
        case 'unixdate':
            $return = strtotime($this->_date);

            break;

        case 'expirestamp':
            $return = strtotime($this->_expire);

            break;

        case 'publish_hour':
            $return = date('H', $this->_date);

            break;

        case 'publish_month':
            $return = date('m', $this->_date);

            break;

        case 'publish_day':
            $return = date('d', $this->_date);

            break;

        case 'publish_year':
            $return = date('Y', $this->_date);

            break;

        case 'public_hour':
            $return = date('H', $this->_date);

            break;

        case 'publish_minute':
            $return = date('i', $this->_date);

            break;

        case 'publish_second':
            $return = date('s', $this->_date);

            break;

        case 'expire_second':
            $return = date('s', $this->_expire);

            break;

        case 'expire_minute':
            $return = date('i', $this->_expire);

            break;

        case 'expire_hour':
            $return = date('H', $this->_expire);

            break;

        case 'expire_day':
            $return = date('d', $this->_expire);

            break;

        case 'expire_month':
            $return = date('m', $this->_expire);

            break;

        case 'expire_year':
            $return = date('Y', $this->_expire);

            break;

        case 'cmt_close':
            $return = ($this->_comment_expire == 0) ? false : true;

            break;

        case 'cmt_close_second':
            if ($this->_comment_expire == 0) {
                $return = date('s', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('s', $this->_comment_expire);
            }

            break;

        case 'cmt_close_minute':
            if ($this->_comment_expire == 0) {
                $return = date('i', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('i', $this->_comment_expire);
            }

            break;

        case 'cmt_close_hour':
            if ($this->_comment_expire == 0) {
                $return = date('H', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('H', $this->_comment_expire);
            }

            break;

        case 'cmt_close_day':
            if ($this->_comment_expire == 0) {
                $return = date('d', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('d', $this->_comment_expire);
            }

            break;

        case 'cmt_close_month':
            if ($this->_comment_expire == 0) {
                $return = date('m', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('m', $this->_comment_expire);
            }

            break;

        case 'cmt_close_year':
            if ($this->_comment_expire == 0) {
                $return = date('Y', time() +
                               ($_CONF['article_comment_close_days'] * 86400));
            } else {
                $return = date('Y', $this->_comment_expire);
            }

            break;

        case 'title':
            $return = $this->_title; //htmlspecialchars($this->_title);

            break;

        case 'page_title':
            $return = $this->_page_title;

            break;

        case 'meta_description':
            $return = $this->_meta_description;

            break;

        case 'meta_keywords':
            $return = $this->_meta_keywords;

            break;

        case 'draft_flag':
            if (isset($this->_draft_flag) && ($this->_draft_flag == 1)) {
                $return = true;
            } else {
                $return = false;
            }

            break;

        case 'cache_time':
            if ($this->_cache_time < -1 OR $this->_cache_time == '') {
                $return  = $_CONF['default_cache_time_article'];
            } else {
                $return = intval($this->_cache_time);
            }

            break;

        case 'introtext':
        case 'bodytext':
            $return = (strtolower($item) == 'introtext') ?
                $this->_introtext : $this->_bodytext;
            if ($this->_text_version == GLTEXT_FIRST_VERSION) {
                $return = $this->replaceImages($return);
            }
            $return = GLText::getEditText(
                          $return,
                          $this->_postmode,
                          $this->_text_version);

            break;

        default:
            $varname = '_' . $item;

            if (isset($this->{$varname})) {
                $return = $this->{$varname};
            } else {
                $return = '';
            }

            break;
        }

        return $return;
    }


    /**
     * Provide access to story elements. For display.
     *
     * This is a peudo-property, implementing a getter for story
     * details as if as an associative array. Personally, I'd
     * rather be able to assign getters and setters to actual
     * properties to mask controlled access to private member
     * variables. But, you get what you get with PHP. So here it
     * is in all it's nastyness.
     *
     * @param   string  $item   Item to fetch.
     * @return  mixed   The clean and ready to use value requested.
     */
    function DisplayElements($item = 'title')
    {
        global $_CONF, $_TABLES;

        $return = '';

        switch (strtolower($item))
        {
        case 'introtext':
        case 'bodytext':
            $return = (strtolower($item) == 'introtext') ?
                $this->_introtext : $this->_bodytext;
            if ($this->_text_version == GLTEXT_FIRST_VERSION) {
                $return = $this->replaceImages($return);
            }
            $return = GLText::getDisplayText(
                          $return,
                          $this->_postmode,
                          $this->_text_version);
            $return = $this->renderImageTags($return);

            break;

        case 'title':
            $return = $this->_displayEscape($this->_title);

            break;

        case 'page_title':
            $return = $this->_displayEscape($this->_page_title);

            break;

        case 'meta_description':
            $return = $this->_meta_description;

            break;

        case 'meta_keywords':
            $return = $this->_meta_keywords;

            break;

        case 'shortdate':
            $return = strftime($_CONF['shortdate'], $this->_date);

            break;

        case 'dateonly':
            $return = strftime($_CONF['dateonly'], $this->_date);

            break;

        case 'date':
            $return = COM_getUserDateTimeFormat($this->_date);

            $return = $return[0];

            break;

        case 'datetime':
             $return = strftime('%FT%T', $this->_date);

             break;

        case 'unixdate':
            $return = $this->_date;

            break;

        case 'hits':
            $return = COM_NumberFormat($this->_hits);

            break;

        case 'topic':
            $return = htmlspecialchars($this->_topic);

            break;

        case 'expire':
            if (empty($this->_expire)) {
                $return = time();
            } else {
                $return = $this->_expire;
            }

            break;

        case 'commentcode':
            // check to see if comment_time has passed
            if ($this->_comment_expire != 0 && (time() > $this->_comment_expire) && $this->_commentcode == 0 ) {
                // if comment code is not 1, change it to 1
                DB_query("UPDATE {$_TABLES['stories']} SET commentcode = '1' WHERE sid = '$this->_sid'");
                $return = 1;
            } else {
                $return = $this->_commentcode;
            }
            break;

        default:
            $varname = '_' . $item;

            if (isset($this->{$varname})) {
                $return = $this->{$varname};
            }

            break;
        }

        return $return;
    }

    /**
     * Returns text ready for preview.
     *
     * @param   string    $item   Item to fetch. Valid only bodytext and introtext.
     * @return  string    text for preview in edit mode
     */
    function getPreviewText($item)
    {
        $text = (strtolower($item) == 'introtext') ?
            $this->_introtext : $this->_bodytext;
        if ($this->_text_version == GLTEXT_FIRST_VERSION) {
            $text = $this->replaceImages($text);
        }
        $text = GLText::getPreviewText(
                    $text,
                    $this->_postmode,
                    'story.edit',
                    $this->_text_version);
        $text = $this->renderImageTags($text);

        return $text;
    }

    /**
     * Perform a security check and return permission level.
     *
     * saves the bother of accessing dozen's of vars.
     *
     * @return  int access level for this story
     */
    function checkAccess()
    {
        return SEC_hasAccess($this->_owner_id, $this->_group_id,
                             $this->_perm_owner, $this->_perm_group,
                             $this->_perm_members, $this->_perm_anon);
    }


    // End Public Methods.

    // Private Methods:

    /**
     * Escapes certain HTML for nicely encoded HTML.
     *
     * @access Private
     * @param   string     $in      Text to escpae
     * @return  string     escaped string
     */
    function _displayEscape($text)
    {
        return str_replace(
            array('$',     '{',      '}',      '\\'),
            array('&#36;', '&#123;', '&#125;', '&#92;'), $text);
    }

    /**
     * Loads the basic details of an article into the internal
     * variables, cleaning them up nicely.
     * @access Private
     * @param $array Array of POST/GET data (by ref).
     * @return Nothing.
     */
    function _loadBasics(&$array)
    {
        /* For the really, really basic stuff, we can very easily load them
         * based on an array that defines how to COM_applyFilter them.
         */
        foreach ($this->_postFields as $key => $value) {
            $vartype = $value[0];
            $varname = $value[1];

            // If we have a value
            if (array_key_exists($key, $array)) {
                // And it's alphanumeric or numeric, filter it and use it.
                if (($vartype == STORY_AL_ALPHANUM) || ($vartype == STORY_AL_NUMERIC)) {
                    $this->{$varname} = COM_applyFilter($array[$key], $vartype);
                } elseif ($vartype == STORY_AL_ANYTHING) {
                    $this->{$varname} = $array[$key];
                } elseif (($array[$key] === 'on') || ($array[$key] === 1)) {
                    // If it's a checkbox that is on
                    $this->{$varname} = 1;
                } else {
                    // Otherwise, it must be a checkbox that is off:
                    $this->{$varname} = 0;
                }
            } elseif (($vartype == STORY_AL_NUMERIC) || ($vartype == STORY_AL_CHECKBOX)) {
                // If we don't have a value, and have a numeric or text box, default to 0
                $this->{$varname} = 0;
            }
        }

        // SID's are a special case:
        $sid = COM_sanitizeID($array['sid']);
        if (isset($array['old_sid'])) {
            $oldsid = COM_sanitizeID($array['old_sid'], false);
        } else {
            $oldsid = '';
        }

        if (empty($sid)) {
            $sid = $oldsid;
        }

        if (empty($sid)) {
            $sid = COM_makeSid();
        }

        $this->_sid = $sid;
        $this->_originalSid = $oldsid;

        /* Need to deal with the postdate and expiry date stuff */
        $publish_ampm = '';
        if (isset($array['publish_ampm'])) {
            $publish_ampm = COM_applyFilter($array['publish_ampm']);
        }
        $publish_hour = 0;
        if (isset($array['publish_hour'])) {
            $publish_hour = COM_applyFilter($array['publish_hour'], true);
        }
        $publish_minute = 0;
        if (isset($array['publish_minute'])) {
            $publish_minute = COM_applyFilter($array['publish_minute'], true);
        }
        $publish_second = 0;
        if (isset($array['publish_second'])) {
            $publish_second = COM_applyFilter($array['publish_second'], true);
        }

        if ($publish_ampm == 'pm') {
            if ($publish_hour < 12) {
                $publish_hour = $publish_hour + 12;
            }
        }

        if ($publish_ampm == 'am' AND $publish_hour == 12) {
            $publish_hour = '00';
        }

        $publish_year = 0;
        if (isset($array['publish_year'])) {
            $publish_year = COM_applyFilter($array['publish_year'], true);
        }
        $publish_month = 0;
        if (isset($array['publish_month'])) {
            $publish_month = COM_applyFilter($array['publish_month'], true);
        }
        $publish_day = 0;
        if (isset($array['publish_day'])) {
            $publish_day = COM_applyFilter($array['publish_day'], true);
        }
        $this->_date = strtotime(
                           "$publish_month/$publish_day/$publish_year $publish_hour:$publish_minute:$publish_second");

        $archiveflag = 0;

        if (isset($array['archiveflag'])) {
            $archiveflag = COM_applyFilter($array['archiveflag'], true);
        }
        /* Override status code if no archive flag is set: */
        if ($archiveflag != 1) {
            $this->_statuscode = 0;
        }

        if (array_key_exists('expire_hour', $array)) {
            $expire_ampm   = COM_applyFilter($array['expire_ampm']);
            $expire_hour   = COM_applyFilter($array['expire_hour'], true);
            $expire_minute = COM_applyFilter($array['expire_minute'], true);
            $expire_second = COM_applyFilter($array['expire_second'], true);
            $expire_year   = COM_applyFilter($array['expire_year'], true);
            $expire_month  = COM_applyFilter($array['expire_month'], true);
            $expire_day    = COM_applyFilter($array['expire_day'], true);

            if ($expire_ampm == 'pm') {
                if ($expire_hour < 12) {
                    $expire_hour = $expire_hour + 12;
                }
            }

            if ($expire_ampm == 'am' AND $expire_hour == 12) {
                $expire_hour = '00';
            }

            $expiredate
            = strtotime("$expire_month/$expire_day/$expire_year $expire_hour:$expire_minute:$expire_second");
        } else {
            $expiredate = time();
        }

        $this->_expire = $expiredate;

        // comment expire time
        if (isset($array['cmt_close_flag'])) {
            $cmt_close_ampm   = COM_applyFilter($array['cmt_close_ampm']);
            $cmt_close_hour   = COM_applyFilter($array['cmt_close_hour'], true);
            $cmt_close_minute = COM_applyFilter($array['cmt_close_minute'], true);
            $cmt_close_second = COM_applyFilter($array['cmt_close_second'], true);
            $cmt_close_year   = COM_applyFilter($array['cmt_close_year'], true);
            $cmt_close_month  = COM_applyFilter($array['cmt_close_month'], true);
            $cmt_close_day    = COM_applyFilter($array['cmt_close_day'], true);

            if ($cmt_close_ampm == 'pm') {
                if ($cmt_close_hour < 12) {
                    $cmt_close_hour = $cmt_close_hour + 12;
                }
            }

            if ($cmt_close_ampm == 'am' AND $cmt_close_hour == 12) {
                $cmt_close_hour = '00';
            }

            $cmt_close_date
            = strtotime("$cmt_close_month/$cmt_close_day/$cmt_close_year $cmt_close_hour:$cmt_close_minute:$cmt_close_second");

            $this->_comment_expire = $cmt_close_date;
        } else {
            $this->_comment_expire = 0;
        }


        /* Then grab the permissions */

        // Convert array values to numeric permission values
        if (is_array($array['perm_owner']) ||
            is_array($array['perm_group']) ||
            is_array($array['perm_members']) ||
            is_array($array['perm_anon'])) {

            list($this->_perm_owner,
                 $this->_perm_group,
                 $this->_perm_members,
                 $this->_perm_anon) =
                     SEC_getPermissionValues(
                         $array['perm_owner'],
                         $array['perm_group'],
                         $array['perm_members'],
                         $array['perm_anon']
                     );
        } else {
            $this->_perm_owner   = $array['perm_owner'];
            $this->_perm_group   = $array['perm_group'];
            $this->_perm_members = $array['perm_members'];
            $this->_perm_anon    = $array['perm_anon'];
        }
    }

    function _applyTitleFilter($title)
    {
//        return htmlspecialchars(strip_tags(COM_checkWords($title)));
        return htmlspecialchars(strip_tags(COM_checkWords($title)),
                                ENT_QUOTES, COM_getEncodingt());
    }

    function _applyTextFilter($text, $postmode)
    {
        if ($this->_text_version == GLTEXT_FIRST_VERSION) {

            // first version

            // Remove any autotags the user doesn't have permission to use
            $text = PLG_replaceTags($text, '', true);
            $text = COM_checkWords($text);

            if (in_array($postmode, array('html', 'adveditor', 'wikitext'))) {
                // html or wikitext
                $text = GLText::checkHTML($text, 'story.edit');
            } else {
                // plaintext
                $text = COM_makeClickableLinks(htmlspecialchars($text));
            }
        } else {

            // latest version

            // Now not do anything here to hold the raw text.
            // And do all of the text processing just before display.
        }

        return $text;
    }

    /**
     * Perform some basic cleanups of data, dealing with empty required,
     * defaultable fields.
     */
    function _sanitizeData()
    {
        if (empty($this->_hits)) {
            $this->_hits = 0;
        }

        if (empty($this->_comments)) {
            $this->_comments = 0;
        }

        if (empty($this->_numemails)) {
            $this->_numemails = 0;
        }

        if (empty($this->_trackbacks)) {
            $this->_trackbacks = 0;
        }

        if ($this->_draft_flag === 'on') {
            $this->_draft_flag = 1;
        } elseif ($this->_draft_flag != 1) {
            $this->_draft_flag = 0;
        }

        if ($this->_show_topic_icon === 'on') {
            $this->_show_topic_icon = 1;
        } elseif ($this->_show_topic_icon != 1) {
            $this->_show_topic_icon = 0;
        }
    }

// End Private Methods.

/**************************************************************************/
}
?>
