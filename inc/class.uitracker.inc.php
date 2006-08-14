<?php
/**
 * Tracker - Universal tracker (bugs, feature requests, ...) with voting and bounties
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/tracker/inc/class.botracker.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.uietemplate.inc.php');

/**
 * User Interface of the tracker
 */
class uitracker extends botracker
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'edit'  => true,
		'index' => true,
		'admin' => true,
	);

	/**
	 * Constructor
	 *
	 * @return botracker
	 */
	function uitracker()
	{
		$this->botracker();
	}
	
	/**
	 * Edit a tracker item in a popup
	 *
	 * @param array $content=null eTemplate content
	 * @param string $msg=''
	 * @param boolean $popup=true use or not use a popup
	 */
	function edit($content=null,$msg='',$popup=true)
	{
		$tabs = 'description|comments|add_comment|links|history';

		if (!is_array($content))
		{
			// edit or new?
			if ((int)$_GET['tr_id'])
			{
				if (!$this->read($_GET['tr_id']))
				{
					$msg = lang('Tracker item not found !!!');
					$this->init();
				}
			}
			else	// new item
			{
				$this->init();
			}
			// for new items use tracker from URL, if availible
			if (!$this->data['tr_id'] && isset($this->trackers[(int)$_GET['tracker']]))
			{
				$this->data['tr_tracker'] = (int)$_GET['tracker'];
			}
			if ($_GET['nopopup']) $popup = false;
			
			if ($popup)
			{
				$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\nwindow.focus();\n</script>\n";
			}
			// check if user has rights to create new entries and fail if not
			if (!$this->data['tr_id'] && !$this->check_rights($this->field_acl['add']))
			{
				$msg = lang('Permission denied !!!');
				if ($popup)
				{
					$GLOBALS['egw']->common->egw_header();
					echo $msg;
					$GLOBALS['egw']->common->egw_exit();
				}
				else
				{
					unset($_GET['tr_id']);	// in case it's still set
					return $this->index(null,$this->data['tr_tracker'],$msg);
				}
				break;
			}
		}
		else	// submitted form
		{
			list($button) = @each($content['button']); unset($content['button']);
			$popup = $content['popup']; unset($content['popup']);

			$this->data = $content;

			switch($button)
			{
				case 'save':
					if (!$this->data['tr_id'] && !$this->check_rights($this->field_acl['add']))
					{
						$msg = lang('Permission denied !!!');
						break;
					}
					if ($this->save() == 0)
					{
						$msg = lang('Entry saved');

						if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
						{
							if (!is_object($GLOBALS['egw']->link))
							{
								$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
							}
							$GLOBALS['egw']->link->link('tracker',$this->data['tr_id'],$content['link_to']['to_id']);
						}
						$js = "opener.location.href=opener.location.href.replace(/&tr_id=[0-9]+/,'')+'&msg=$msg&tracker=$content[tr_tracker]';";
					}
					else
					{
						$msg = lang('Error saving the entry!!!');
						break;
					}
					// fall-through for save
				case 'cancel':
					if ($popup)
					{
						$js .= 'window.close();';
						echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					else
					{
						unset($_GET['tr_id']);	// in case it's still set
						return $this->index(null,$this->data['tr_tracker'],$msg);
					}
					break;

				case 'vote':
					if ($button == 'vote' && $this->cast_vote())
					{
						$msg = lang('Thank you for voting.');
						if ($popup)
						{
							$js = "opener.location.href=opener.location.href.replace(/&tr_id=[0-9]+/,'')+'&msg=$msg';";
							$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\n$js\n</script>\n";
						}
					}
					break;
			}
		}
		$tr_id = $this->data['tr_id'];
		if (!($tracker = $this->data['tr_tracker'])) list($tracker) = @each($this->trackers);

		$preserv = $content = $this->data;
		if ($content['num_replies']) array_unshift($content['replies'],false);	// need array index starting with 1!
		$content += array(
			'msg' => $msg,
			'on_cancel' => $popup ? 'window.close();' : '',
			'no_vote' => '',
			'link_to' => array(
				'to_id' => $tr_id,
				'to_app' => 'tracker',
			),
			'history' => array(
				'id'  => $tr_id,
				'app' => 'tracker',
				'status-widgets' => array(
					'Co' => 'select-percent',
					'St' => &$this->stati,
					'Pr' => &$this->priorities,
					'Ca' => 'select-category',
					'Tr' => 'select-category',
					'Ve' => 'select-category',
					'As' => 'select-account',
					'pr' => array('Public','Private'),
					'Cl' => 'date-time',
					'Re' => &$this->resolutions,
				),
			),
		);
		$preserv['popup'] = $popup;

		if (!$tr_id && isset($_REQUEST['link_app']) && isset($_REQUEST['link_id']) && !is_array($content['link_to']['to_id']))
		{
			if (!is_object($GLOBALS['egw']->link))
			{
				$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
			}
			$link_ids = is_array($_REQUEST['link_id']) ? $_REQUEST['link_id'] : array($_REQUEST['link_id']);
			foreach(is_array($_REQUEST['link_app']) ? $_REQUEST['link_app'] : array($_REQUEST['link_app']) as $n => $link_app)
			{
				$link_id = $link_ids[$n];
				if (preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$link_app.':'.$link_id))	// gard against XSS
				{
					$GLOBALS['egw']->link->link('tracker',$content['link_to']['to_id'],$link_app,$link_id);
				}
			}
		}
		$sel_options = array(
			'tr_tracker'  => &$this->trackers,
			'cat_id'      => $this->get_tracker_labels('cat',$tracker),
			'tr_version'  => $this->get_tracker_labels('version',$tracker),
			'tr_priority' => &$this->priorities,
			'tr_status'   => &$this->stati,
			'tr_resolution' => &$this->resolutions,
			'tr_assigned' => $this->get_staff($tracker,$this->allow_assign_groups),
		);
		foreach($this->field2history as $field => $status)
		{
			$sel_options['status'][$status] = $this->field2label[$field];
		}
		$readonlys = $this->readonlys_from_acl();
		$readonlys[$tabs] = array(
			'comments' => !$tr_id || !$content['num_replies'],
			'add_comment' => !$tr_id || $readonlys['reply_message'],
			'history'  => !$tr_id,
		);
		if ($tr_id && $readonlys['reply_message'])
		{
			$readonlys['button[save]'] = true;
		}
		if (!$tr_id && $readonlys['add'])
		{
			$msg = lang('Permission denied !!!');
			$readonlys['button[save]'] = true;
		}
		if (!$this->allow_voting || !$tr_id || $readonlys['vote'] || ($voted = $this->check_vote()))
		{
			$readonlys['button[vote]'] = true;
			if ($tr_id && $this->allow_voting)
			{
				$content['no_vote'] = is_int($voted) ? lang('You voted %1.',
					date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].
					($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?' h:i a':' H:i')),$voted) :
					lang('You need to login to vote!');
			}
		}
		$content['no_links'] = $readonlys['link_to'];
		// ToDo: implement canned responses
		if (true || $readonlys['canned_response'])
		{
			$content['no_canned'] = true;
		}
		$what = $tracker ? $this->trackers[$tracker] : lang('Tracker');
		$GLOBALS['egw_info']['flags']['app_header'] = $tr_id ? lang('Edit %1',$what) : lang('New %1',$what);

		$tpl =& new etemplate('tracker.edit');

		return $tpl->exec('tracker.uitracker.edit',$content,$sel_options,$readonlys,$preserv,$popup ? 2 : 0);
	}
	
	/**
	 * set fields readonly, depending on the rights the current user has on the actual tracker item
	 *
	 * @return array
	 */
	function readonlys_from_acl()
	{
		//echo "<p>uitracker::get_readonlys() is_admin(tracker={$this->data['tr_tracker']})=".$this->is_admin($this->data['tr_tracker']).", id={$this->data['tr_id']}, creator={$this->data['tr_creator']}, assigned={$this->data['tr_assigned']}, user=$this->user</p>\n";
		$readonlys = array();
		foreach($this->field_acl as $name => $rigths)
		{
			$readonlys[$name] = !$this->check_rights($rigths);
		}
		//_debug_array($readonlys);
		return $readonlys;
	}
	
	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl
	 * @return int total number of rows
	 */
	function get_rows(&$query_in,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('index','tracker',$query=$query_in);

		//if (!$query['tr_tracker']) list($query['tr_tracker']) = each($this->trackers);
		$tracker = $query['col_filter']['tr_tracker'];// = $query['tr_tracker'];
		if (!($query['col_filter']['cat_id'] = $query['filter'])) unset($query['col_filter']['cat_id']);
		if (!($query['col_filter']['tr_version'] = $query['filter2'])) unset($query['col_filter']['tr_version']);

		if ($query['col_filter']['tr_assigned'] < 0)	// resolve groups with it's members
		{
			$query['col_filter']['tr_assigned'] = $GLOBALS['egw']->accounts->members($query['col_filter']['tr_assigned'],true);
			$query['col_filter']['tr_assigned'][] = $query_in['col_filter']['tr_assigned'];
		}
		//echo "<p align=right>uitracker::get_rows() order='$query[order]', sort='$query[sort]', search='$query[search]', start=$query[start], num_rows=$query[num_rows], col_filter=".print_r($query['col_filter'],true)."</p>\n";
		$total = parent::get_rows($query,$rows,$readonlys,$this->allow_voting);	// true = join votes table
		
		foreach($rows as $n => $row)
		{
			if ($row['overdue']) $rows[$n]['overdue_class'] = 'overdue';
		}
		// set the options for assigned to depending on the tracker
		$rows['sel_options']['tr_assigned'] = array('not' => lang('Not assigned'))+$this->get_staff($tracker,2,true);
		$rows['sel_options']['filter'] = array(lang('All'))+$this->get_tracker_labels('cat',$tracker);
		$rows['sel_options']['filter2'] = array(lang('All'))+$this->get_tracker_labels('version',$tracker);
		$rows['allow_voting'] = $this->allow_voting;
		$rows['no_cat'] = $query['col_filter']['cat_id'];
		
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Tracker').': '.$this->trackers[$tracker];

		return $total;
	}

	/**
	 * Show a tracker
	 *
	 * @param array $content=null eTemplate content
	 * @param int $tracker=null id of tracker
	 * @param string $msg=''
	 */
	function index($content=null,$tracker=null,$msg='')
	{
		if (!is_array($content))
		{
			if ($_GET['tr_id'])
			{
				if (!$this->read($_GET['tr_id']))
				{
					$msg = lang('Tracker item not found !!!');
				}
				else
				{
					return $this->edit(null,'',false);	// false = use no popup
				}
			}
			if (!$msg && $_GET['msg']) $msg = $_GET['msg'];
			if (!$tracker && (int)$_GET['tracker']) $tracker = $_GET['tracker'];
		}
		if (!$tracker) $tracker = $content['nm']['col_filter']['tr_tracker'];

		$sel_options = array(
			'tr_tracker'  => &$this->trackers,
			'tr_priority' => &$this->priorities,
			'tr_status'   => &$this->stati,
		);
		$content = array(
			'nm' => $GLOBALS['egw']->session->appsession('index','tracker'),
			'msg' => $msg,
		);		
		if (!$tracker) $tracker = $content['nm']['col_filter']['tr_tracker'];
		if (!$tracker) list($tracker) = @each($this->trackers);

		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'tracker.uitracker.get_rows',
				'no_cat'         => true,
				'filter2'        => '',	// all
				'filter2_label'  => lang('Version'),
				'filter2_no_lang'=> true,
				'filter'         => '', // all
				'filter_label'   => lang('Category'),
				'filter_no_lang' => true,
				'order'          =>	$this->allow_voting ? 'votes' : 'tr_id',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'options-tr_assigned' => array('not' => lang('Noone')),
				'col_filter'     => array(
					'tr_status'  => 'o',	// default filter: open
				),
	 			'header_left'    =>	'tracker.index.left', // I  template to show left of the range-value, left-aligned (optional)
			);
		}
		$content['nm']['col_filter']['tr_tracker'] = $tracker;
		
		$readonlys['add'] = !$this->check_rights($this->field_acl['add'],$tracker);

		$tpl =& new etemplate('tracker.index');

		return $tpl->exec('tracker.uitracker.index',$content,$sel_options,$readonlys);
	}
	
	/**
	 * Site configuration
	 *
	 * @param array $content=null
	 * @return string
	 */
	function admin($content=null,$msg='')
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$GLOBALS['egw']->common->egw_header();
			parse_navbar();
			echo '<h1 style="color: red;">'.lang('Permission denied !!!')."</h1>\n";
			$GLOBALS['egw']->common->egw_footer();
			return;
		}
		_debug_array($content);
		$tracker = (int) $content['tracker'];

		if (is_array($content))
		{
			list($button) = @each($content['button']);
			
			switch($button)
			{
				case 'add':
					if (!$content['add_name'])
					{
						$msg = lang('You need to enter a name');
					}
					elseif (($id = $this->add_tracker($content['add_name'])))
					{
						$tracker = $id;
						$msg = lang('Tracker added');
					}
					else
					{
						$msg = lang('Error adding the new tracker!');
					}
					break;

				case 'delete':
					if ($tracker && isset($this->trackers[$tracker]))
					{
						$this->delete_tracker($tracker);
						$tracker = 0;
						$msg = lang('Tracker deleted');
					}
					break;

				case 'apply':
				case 'save':
					$need_update = false;
					if (!$tracker)	// tracker unspecific config
					{
						foreach(array_diff($this->config_names,array('field_acl','technicians','admins')) as $name)
						{
							if ($this->$name != $content[$name])
							{
								$this->$name = $content[$name];
								$need_update = true;
							}
						}
						// field_acl				
						foreach($content['field_acl'] as $row)
						{
							$rights = 0;
							foreach(array(
								'TRACKER_ADMIN'         => TRACKER_ADMIN,
								'TRACKER_TECHNICIAN'    => TRACKER_TECHNICIAN,
								'TRACKER_USER'          => TRACKER_USER,
								'TRACKER_EVERYBODY'     => TRACKER_EVERYBODY,
								'TRACKER_ITEM_CREATOR'  => TRACKER_ITEM_CREATOR,
								'TRACKER_ITEM_ASSIGNEE' => TRACKER_ITEM_ASSIGNEE,
								'TRACKER_ITEM_NEW'      => TRACKER_ITEM_NEW,							
							) as $name => $right)
							{
								if ($row[$name]) $rights |= $right;
							}
							if ($this->field_acl[$row['name']] != $rights)
							{
								//echo "<p>$row[name] / $row[label]: rights: ".$this->field_acl[$row['name']]." => $rights</p>\n";
								$this->field_acl[$row['name']] = $rights;
								$need_update = true;
							}
						}
					}
					foreach(array('technicians','admins') as $name)
					{
						$staff =& $this->$name;
						if (!isset($staff[$tracker])) $staff[$tracker] = array();
						if (!isset($content[$name])) $content[$name] = array();

						if ($staff[$tracker] != $content[$name])
						{
							$staff[$tracker] = $content[$name];
							$need_update = true;
						}
					}
					if ($need_update)
					{
						$this->save_config();
						$msg = lang('Configuration updated.').' ';
					}
					$need_update = false;
					foreach(array('cats','versions') as $name)
					{
						foreach($content[$name] as $cat)
						{
							if (!$cat['name']) continue;	// ignore empty (new) cats

							$old_cat = array(	// some defaults for new cats
								'main'   => $tracker,
								'parent' => $tracker,
								'access' => 'public',
								'data'   => array('type' => $name == 'cats' ? 'cat' : 'version'),
								'descr'  => 'tracker-'.($name == 'cats' ? 'cat' : 'version'),
							);
							// search cat in existing ones
							foreach($this->all_cats as $c)
							{
								if ($cat['id'] == $c['id'])
								{
									$old_cat = $c;
									$old_cat['data'] = unserialize($old_cat['data']);
									break;
								}
							}
							// check if new cat or changed
							if (!$old_cat || $cat['name'] != $old_cat['name'] || 
								$name == 'cats' && (int)$cat['autoassign'] != (int)$old_cat['data']['autoassign'])
							{
								$old_cat['name'] = $cat['name'];
								if ($cat['autoassign']) $old_cat['data']['autoassign'] = $cat['autoassign'];
								//echo "update to"; _debug_array($old_cat);
								$old_cat['data'] = serialize($old_cat['data']);
								$GLOBALS['egw']->categories->account_id = -1;	// global cat!
								if (($id = $GLOBALS['egw']->categories->add($old_cat)))
								{
									$what = $name == 'cats' ? lang('Category') : lang('Version');
									$msg .= $old_cat['id'] ? lang("Tracker-%1 '%2' updated.",$what,$cat['name']) : lang("Tracker-%1 '%2' added.",$what,$cat['name']);
									$need_update = true;
								}
							}
						}
					}
					if ($need_update)
					{
						$this->reload_labels();
					}
					if ($button == 'apply') break;
					// fall-through for save
				case 'cancel':
					$GLOBALS['egw']->redirect_link('/index.php',array(
						'menuaction' => 'tracker.uitracker.index',
						'msg' => $msg,
					));
					break;
					
				default:
					foreach(array('cats','versions') as $name)
					{
						if (isset($content[$name]['delete']))
						{
							list($id) = each($content[$name]['delete']);
							if ((int)$id)
							{
								$GLOBALS['egw']->categories->delete($id);
								$msg = lang('Tracker-%1 deleted.',$name == 'cats' ? lang('Category') : lang('Version'));
								$this->reload_labels();
							}
						}
					}
					break;
			}
			
		}
		$content = array(
			'msg' => $msg,
			'tracker' => $tracker,
			'admins' => $this->admins[$tracker],
			'technicians' => $this->technicians[$tracker],
		);
		foreach($this->config_names as $name)
		{
			if (!isset($content[$name])) $content[$name] = $this->$name;
		}
		// cats & versions
		$v = $c = 1;
		usort($this->all_cats,create_function('$a,$b','return strcasecmp($a["name"],$b["name"]);'));
		foreach($this->all_cats as $cat)
		{
			if (!($data = unserialize($cat['data']))) $data = array();
			//echo "<p>$cat[name] ($cat[id]/$cat[parent]/$cat[main]): ".print_r($data,true)."</p>\n";

			if ($cat['parent'] == $tracker && $data['type'] != 'tracker')
			{
				if ($data['type'] == 'version')
				{
					$content['versions'][$v++] = $cat + $data;
				}
				else // cat
				{
					$data['type'] = 'cat';
					$content['cats'][$c++] = $cat + $data;
				}
			}
		}
		$content['versions'][$v++] = $content['cats'][$c++] = array('id' => 0,'name' => '');	// one empty line for adding
		// field_acl
		$f = 1;
		foreach($this->field2label as $name => $label)
		{
			if ($name == 'tr_creator') continue;

			$rights = $this->field_acl[$name];
			$content['field_acl'][$f++] = array(
				'label'                 => $label,
				'name'                  => $name,
				'TRACKER_ADMIN'         => !!($rights & TRACKER_ADMIN),
				'TRACKER_TECHNICIAN'    => !!($rights & TRACKER_TECHNICIAN),
				'TRACKER_USER'          => !!($rights & TRACKER_USER),
				'TRACKER_EVERYBODY'     => !!($rights & TRACKER_EVERYBODY),
				'TRACKER_ITEM_CREATOR'  => !!($rights & TRACKER_ITEM_CREATOR),
				'TRACKER_ITEM_ASSIGNEE' => !!($rights & TRACKER_ITEM_ASSIGNEE),
				'TRACKER_ITEM_NEW'      => !!($rights & TRACKER_ITEM_NEW),
			);
		}
		//_debug_array($content);
		$sel_options = array(
			'tracker' => &$this->trackers,
			'allow_assign_groups' => array(
				0 => lang('No'),
				1 => lang('Yes, display groups first'),
				2 => lang('Yes, display users first'),
			),
			'allow_voting' => array('No','Yes'),
			'autoassign' => $this->get_staff($tracker),
			'notification_lang' => $GLOBALS['egw']->translation->get_installed_langs(),
		);
		$readonlys = array(
			'button[delete]' => !$tracker,
			'delete[0]' => true,
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Tracker configuration');
		$tpl =& new etemplate('tracker.admin');

		return $tpl->exec('tracker.uitracker.admin',$content,$sel_options,$readonlys,$content);
	}
}