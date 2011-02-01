;(function($) {
/**
 * A workaround for $.post not passing the XHR to the callback function.
 */
var __last_xhr;

$(function() {
	$("#ModelAdminPanel").fn({
		/**
		* Loads a new form from a URL, and displays the status message attached
		* to the response. This overloads the default ModelAdmin implementation
		* to allow attaching data to the request.
		*
		* @param {String} url
		* @param {Object} data
		* @param {Function} callback
		*/
		loadForm: function(url, data, callback) {
			statusMessage(ss.i18n._t("ExternalContent.LOADING", "Loading..."));

			$(this).load(url, data, responseHandler(function(response, status, xhr) {
				$("#form_actions_right").remove();
				Behaviour.apply();
				if(window.onresize) window.onresize();

				if(callback) $(this).each(callback, [response, status, xhr]);
			}));
		}
	});

	/**
	 * Initialise the external content tree.
	 */
	$("#ExternalItems").jstree({
		plugins: [ "themes", "json_data", "ui" ],
		json_data: { ajax: {
			url: function() {
				return this.get_container().attr("href");
			},
			data: function(n) {
				return { "id": n != -1 ? n.data("jstree").id : 0 };
			}
		}}
	});

	$("#ExternalItems").bind("select_node.jstree", function(event, args) {
		var $link = $(args.args[0]).addClass("jstree-loading");

		$("#ModelAdminPanel").fn("loadForm", $link.attr("href"), {}, function() {
			$link.removeClass("jstree-loading");
		});

		return false;
	});

	$("#Form_CreateSourceForm").submit(function() {
		var $form = $(this);

		$("#ExternalItems").jstree("close_all", -1);
		$("#ModelAdminPanel").fn("loadForm", $form.attr("action"), $form.formToArray(), function() {
			$(".loading", $form).removeClass("loading");
			$("#ExternalItems").jstree("refresh", -1);
			$("#ToggleCreateForm").parent().removeClass("selected");
			$("#Form_CreateSourceForm").hide();
		});

		return false;
	});

	/**
	 * Hides and shows the create source form.
	 */
	$("#ToggleCreateForm").click(function() {
		$(this).parent().toggleClass("selected");

		if ($(this).parent().hasClass("selected")) {
			$("#Form_CreateSourceForm").show();
		} else {
			$("#Form_CreateSourceForm").hide();
		}
	});
});

$("#form_actions_right input").live("click", function() {
	var button = $(this).addClass("loading");
	var form = $("#right form");
	var action = form.attr("action") + "?" + $(this).fieldSerialize();
	var isDelete = ($(this).attr("name") == "action_doDelete");

	if(isDelete) {
		var msg = ss.i18n._t("ExternalContent.DELETECONNECTOR",
				"Do you really want to delete this connector?");

		if(!confirm(msg)) {
			button.removeClass("loading");
			return false;
		}
	} else {
		if(typeof tinyMCE != "undefined") tinyMCE.triggerSave();
	}

	$("#ModelAdminPanel").fn("loadForm", action, form.formToArray(), responseHandler(function() {
		button.removeClass("loading");

		if(!isDelete) {
			if($("#right #ModelAdminPanel form").hasClass("validationerror")) {
				errorMessage(ss.i18n._t("ExternalContent.VALIDATIONERROR", "Validation Error"));
				return;
			} else {
				statusMessage(ss.i18n._t("ExternalContent.SAVED", "Saved"), "good");
			}
		}

		$("#ExternalItems").jstree("refresh", -1);
	}));

	return false;
});

/**
 * A simple wrapper around an AJAX request response handler function that shows
 * the status text attached to the response as a status message.
 *
 * @param   {Function} callback
 * @returns Function
 */
function responseHandler(callback) {
	return function(response, status, xhr) {
		if(status == "success") {
			statusMessage(xhr.statusText, "good");
		} else {
			errorMessage(xhr.statusText);
		}

		if(callback) $(this).each(callback, [response, status, xhr]);
	}
}
})(jQuery);