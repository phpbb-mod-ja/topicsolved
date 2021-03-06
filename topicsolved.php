<?php
/**
 * This file is part of the phpBB Topic Solved extension package.
 *
 * @copyright (c) Bryan Petty
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * @package tierra/topicsolved
 */

namespace tierra\topicsolved;

/**
 * Core topic solved functionality used throughout the extension.
 *
 * @package tierra/topicsolved
 */
class topicsolved
{
	/** Topic starter and moderators can mark topics as solved. */
	const TOPIC_SOLVED_YES = 1;

	/** Only moderators can mark topics as solved. */
	const TOPIC_SOLVED_MOD = 2;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface $db Database object
	 * @param \phpbb\user $user
	 * @param \phpbb\auth\auth $auth
	 */
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\user $user,
		\phpbb\auth\auth $auth)
	{
		$this->db = $db;
		$this->user = $user;
		$this->auth = $auth;

		$this->user->add_lang_ext('tierra/topicsolved', 'common');
	}

	/**
	 * Determine if user is allowed to mark a post as solved or unsolved.
	 *
	 * @param string $solved Either "solved" or "unsolved".
	 * @param array $topic_data Topic to be solved or unsolved.
	 *
	 * @throws \phpbb\exception\runtime_exception
	 *         if an invalid solved parameter is specified.
	 *
	 * @return bool User is authorized to (un)solve topic.
	 */
	public function user_can_solve_post($solved, $topic_data)
	{
		if ($solved == 'solved')
		{
			$forum_permission = 'forum_allow_solve';
		}
		else if ($solved == 'unsolved')
		{
			$forum_permission = 'forum_allow_unsolve';
		}
		else
		{
			throw new \phpbb\exception\runtime_exception(
				'BAD_METHOD_CALL', array('user_can_solve_post'));
		}

		// Disallow (un)solving topic if post is global.
		if ($topic_data['topic_type'] == POST_GLOBAL)
		{
			return false;
		}

		if (($topic_data[$forum_permission] == topicsolved::TOPIC_SOLVED_MOD ||
			$topic_data[$forum_permission] == topicsolved::TOPIC_SOLVED_YES) &&
			$this->auth->acl_get('m_'))
		{
			return true;
		}
		else if ($topic_data[$forum_permission] == topicsolved::TOPIC_SOLVED_YES &&
			$topic_data['topic_poster'] == $this->user->data['user_id'] &&
			$topic_data['topic_status'] == ITEM_UNLOCKED)
		{
			return true;
		}

		return false;
	}

	/**
	 * Fetches all topic solved data related to the given post.
	 *
	 * @param int $post_id ID of post to fetch topic/forum data for.
	 *
	 * @return mixed topic data, or false if not found
	 */
	public function get_topic_data($post_id)
	{
		$select_sql_array = array(
			'SELECT' =>
				't.topic_id, t.topic_poster, t.topic_status, t.topic_type, t.topic_solved, ' .
				'f.forum_id, f.forum_allow_solve, f.forum_allow_unsolve, f.forum_lock_solved, ' .
				'p.post_id',
			'FROM' => array(
				FORUMS_TABLE => 'f',
				POSTS_TABLE => 'p',
				TOPICS_TABLE => 't',
			),
			'WHERE' =>
				'p.post_id = ' . $this->db->sql_escape($post_id) .
				' AND t.topic_id = p.topic_id AND f.forum_id = t.forum_id',
		);
		$select_sql = $this->db->sql_build_query('SELECT', $select_sql_array);
		$result = $this->db->sql_query($select_sql);
		$topic_data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $topic_data;
	}

	/**
	 * Mark topic as solved with the given post.
	 *
	 * Post will only be locked if marking as solved, not when unsolving. This
	 * method does not do any validation. Data should be validated first.
	 *
	 * @param int $topic_id Topic to be marked.
	 * @param int $post_id Solved post or 0 for unsolved topic.
	 * @param bool $lock Lock the topic after marking as solved.
	 *
	 * @return mixed true if successful
	 */
	public function update_topic_solved($topic_id, $post_id, $lock = false)
	{
		$data = array('topic_solved' => $post_id);

		if ($lock)
		{
			$data['topic_status'] = ITEM_LOCKED;
		}

		$update_sql = $this->db->sql_build_array('UPDATE', $data);
		$result = $this->db->sql_query('
			UPDATE ' . TOPICS_TABLE . '
			SET ' . $update_sql . '
			WHERE topic_id = ' . $topic_id
		);

		return $result;
	}
}
