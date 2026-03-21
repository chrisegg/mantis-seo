/**
 * After Quick Edit inline-save, show notice when Mantis auto-created a permalink redirect.
 */
( function ( $ ) {
	var config = window.mantisSeoRedirectNotice;
	if ( ! config || ! config.ajaxUrl ) {
		return;
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
				showAdminNotice( n.message, n.viewUrl || '' );
			} )
			.catch( function () {} );
	}

	function showAdminNotice( message, viewUrl ) {
		$( '#mantis-seo-inline-redirect-notice' ).remove();

		var label = config.viewRedirectLabel || 'View redirect';
		var sr = config.dismissNoticeSr || 'Dismiss this notice.';

		var $p = $( '<p />' );
		$p.text( message );
		if ( viewUrl ) {
			$p.append( ' ' );
			$( '<a />', { href: viewUrl, text: label } ).appendTo( $p );
		}

		var $n = $( '<div />', {
			id: 'mantis-seo-inline-redirect-notice',
			class: 'notice notice-success is-dismissible',
		} );
		$n.append( $p );
		var $btn = $( '<button type="button" class="notice-dismiss" />' );
		$btn.append( $( '<span class="screen-reader-text" />' ).text( ' ' + sr ) );
		$n.append( $btn );

		$n.on( 'click', '.notice-dismiss', function () {
			$n.remove();
		} );

		var $h = $( '.wrap' ).first().find( 'h1' ).first();
		if ( $h.length ) {
			$h.after( $n );
		} else {
			$( '.wrap' ).first().prepend( $n );
		}

		if ( window.wp && wp.a11y && typeof wp.a11y.speak === 'function' ) {
			wp.a11y.speak( message );
		}
	}

	$( document ).ajaxSuccess( function ( event, xhr, settings ) {
		var data = settings.data;
		if ( typeof data !== 'string' || data.indexOf( 'action=inline-save' ) === -1 ) {
			return;
		}
		if ( xhr.status !== 200 ) {
			return;
		}
		var rt = ( xhr.responseText || '' ).trim();
		if ( rt === '-1' || rt === '0' ) {
			return;
		}
		var m = data.match( /(?:^|&)post_ID=(\d+)/ );
		if ( ! m ) {
			return;
		}
		var postId = parseInt( m[ 1 ], 10 );
		if ( postId ) {
			fetchNotice( postId );
		}
	} );
} )( jQuery );
