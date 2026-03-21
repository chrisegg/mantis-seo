/**
 * After a block-editor save, show a notice when Mantis auto-created a permalink redirect.
 */
( function () {
	var config = window.mantisSeoEditorRedirect;
	if ( ! config || ! config.ajaxUrl ) {
		return;
	}

	function showNotice( message, viewUrl ) {
		if ( ! window.wp || ! wp.data || ! wp.data.dispatch ) {
			return;
		}
		var dispatch = wp.data.dispatch( 'core/notices' );
		if ( ! dispatch || typeof dispatch.createSuccessNotice !== 'function' ) {
			return;
		}
		var opts = { isDismissible: true };
		if ( viewUrl ) {
			opts.type = 'snackbar';
			opts.actions = [
				{ label: config.viewRedirectLabel || 'View redirect', url: viewUrl },
			];
		}
		dispatch.createSuccessNotice( message, opts );
	}

	function fetchNotice( postId ) {
		var body =
			'action=mantis_seo_editor_redirect_notice' +
			'&nonce=' +
			encodeURIComponent( config.nonce ) +
			'&post_id=' +
			encodeURIComponent( String( postId ) );

		fetch( config.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: body,
			credentials: 'same-origin',
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( resp ) {
				if ( ! resp || ! resp.success || ! resp.data || ! resp.data.notice ) {
					return;
				}
				var n = resp.data.notice;
				showNotice( n.message, n.viewUrl || '' );
			} )
			.catch( function () {} );
	}

	wp.domReady( function () {
		if ( ! window.wp || ! wp.data || typeof wp.data.subscribe !== 'function' || typeof wp.data.select !== 'function' ) {
			return;
		}
		var select = wp.data.select;
		var wasSaving = false;
		wp.data.subscribe( function () {
			var editor = select( 'core/editor' );
			if ( ! editor || typeof editor.isSavingPost !== 'function' ) {
				return;
			}
			var saving = editor.isSavingPost();
			if ( wasSaving && ! saving ) {
				if ( typeof editor.didPostSaveFail === 'function' && editor.didPostSaveFail() ) {
					wasSaving = saving;
					return;
				}
				var postId =
					typeof editor.getCurrentPostId === 'function' ? editor.getCurrentPostId() : 0;
				if ( postId ) {
					fetchNotice( postId );
				}
			}
			wasSaving = saving;
		} );
	} );
} )();
