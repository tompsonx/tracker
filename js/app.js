/**
 * EGroupware - Tracker - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package tracker
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version
 */

/**
 * UI for tracker
 *
 * @augments AppJS
 */
app.tracker = AppJS.extend(
{
	appname: 'tracker',
	/**
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */

	/**
	 * Constructor
	 *
	 * @memberOf app.tracker
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);
		if (typeof et2.templates['tracker.admin'] != "undefined")
		{
			this.acl_queue_access();
		}

		if (typeof et2.templates['tracker.edit'] != "undefined")
		{
			this.edit_popup();
		}
	},


	/**
	 * add_email_from_ab
	 * egw:uses
	 * /phpgwapi/js/jsapi/app_base;
	 * @param ab_id
	 * @param tr_cc
	 */
	add_email_from_ab: function(ab_id,tr_cc)
	{
		var ab = document.getElementById(ab_id);

		if (!ab || !ab.value)
		{
			$j('tr.hiddenRow').css('display','table-row');
		}
		else
		{
			var cc = document.getElementById(tr_cc);

			for(var i=0; i < ab.options.length && ab.options[i].value != ab.value; ++i) ;

			if (i < ab.options.length)
			{
				cc.value += (cc.value?', ':'')+ab.options[i].text.replace(/^.* <(.*)>$/,'$1');
				ab.value = '';
				ab.onchange();
				$j('tr.hiddenRow').css('display','none');
			}
		}
		return false;
	},

	/**
	 * expand_filter
	 * Used in escalations on buttons to change filters from a single select to a multi-select
	 * @param  filter
	 * @param  widget
	 */
	expand_filter: function(filter,widget)
	{
		$j(this).hide();
		var selectbox=document.getElementById(filter)
		if ($j('[id="'+filter+'"]').length != 1 && typeof widget != "undefined" && widget != null)
		{
			selectbox = widget.getParent().getWidgetById(filter).getInputNode();
			widget.getParent().getWidgetById(filter).set_tags(true);
		}
		else if (selectbox)
		{
			selectbox.name+='[]';
		}

		if($j().chosen)
		{
			$j(selectbox).unchosen();
		}
		selectbox.size=3;
		selectbox.multiple=true;
		if(selectbox.options[0].value=='')
		{
			selectbox.options[0]=null;
		}
		if($j().chosen) $j(selectbox).chosen();

		return false;
	},

	/**
	 * tprint
	 * @param _action
	 * @param _senders
	 */
	tprint: function(_action,_senders)
	{

		var id = _senders[0].id.split('::');
		if (_action.id === 'print')
		{
			var popup  = egw().open_link('/index.php?menuaction=tracker.tracker_ui.tprint&tr_id='+id[1],'',egw().link_get_registry('tracker','add_popup'),'tracker');
			popup.print();
		}
	},

	/**
	 * edit_popup
	 * Check if the edit window is a popup, then set window focus
	 */
	edit_popup: function()
	{
		if (!this.et2.node.baseURI.match('[no][no_]popup'))
		{
			window.focus();
			if (this.et2.node.baseURI.match('composeid')) //tracker created by mail application
			{
				window.resizeTo(750,550);
			}
		}
	},

	/**
	 * canned_comment_requst
	 *
	 */
	canned_comment_requst: function()
	{
		var editor = this.et2.getWidgetById('reply_message');
		var id = this.et2.getWidgetById('canned_response').get_value();
		if (id && editor)
		{
			var request = new egw_json_request('tracker.tracker_ui.ajax_canned_comment',[id,document.getElementById('tracker-edit_reply_message').style.display == 'none']);
			request.sendRequest(true);
		}
	},
	/**
	 * canned_comment_response
	 * @param _replyMsg
	 */
	canned_comment_response: function(_replyMsg)
	{
		var editor = this.et2.getWidgetById('reply_message');
		if(editor)
		{
			editor.set_value(_replyMsg.replace(/(\r\n|\n|\r)/gm,""));
		}
	},
	/**
	 * acl_queue_access
	 */
	acl_queue_access: function()
	{

		if(this.et2.getWidgetById('enabled_queue_acl_access').get_value() === 'false')
		{

			this.et2.getWidgetById('users').disabled = true;
		}
		else
		{
			this.et2.getWidgetById('users').disabled = false;
		}
	},
});