/**
 * Member View front-end: intercept same-domain links, open an accessible login
 * modal, and drive the login / request-access AJAX. Loaded only for gated
 * users.
 */
( function () {
	'use strict';

	var cfg = window.MemberView || {};
	var overlay = document.querySelector( '[data-member-view-overlay]' );
	var dialog = overlay ? overlay.querySelector( '[data-member-view-dialog]' ) : null;
	if ( ! overlay || ! dialog ) {
		return;
	}

	var lastFocused = null;

	function focusable() {
		return Array.prototype.slice.call(
			dialog.querySelectorAll(
				'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), [tabindex]:not([tabindex="-1"])'
			)
		).filter( function ( el ) {
			return el.offsetParent !== null;
		} );
	}

	function openModal() {
		lastFocused = document.activeElement;
		overlay.hidden = false;
		document.body.classList.add( 'member-view-open' );
		var f = focusable();
		if ( f.length ) {
			f[ 0 ].focus();
		}
		document.addEventListener( 'keydown', onKeydown );
	}

	function closeModal() {
		overlay.hidden = true;
		document.body.classList.remove( 'member-view-open' );
		document.removeEventListener( 'keydown', onKeydown );
		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	function onKeydown( e ) {
		if ( e.key === 'Escape' ) {
			closeModal();
			return;
		}
		if ( e.key !== 'Tab' ) {
			return;
		}
		var f = focusable();
		if ( ! f.length ) {
			return;
		}
		var first = f[ 0 ];
		var last = f[ f.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	/**
	 * Should this anchor click be intercepted into the modal?
	 */
	function shouldIntercept( link ) {
		if ( ! link || ! link.getAttribute ) {
			return false;
		}

		// Explicit opt-out marker.
		if ( link.hasAttribute( 'data-member-view-allow' ) || link.classList.contains( 'member-view-allow' ) ) {
			return false;
		}

		// Elements wired to the modal itself.
		if ( link.closest( '[data-member-view-overlay]' ) || link.hasAttribute( 'data-member-view-open' ) ) {
			return false;
		}

		// Never hijack the admin bar (a previewing admin still needs it to exit).
		if ( link.closest( '#wpadminbar' ) ) {
			return false;
		}

		var href = link.getAttribute( 'href' );
		if ( ! href ) {
			return false;
		}

		// Pure fragment / same-page anchors.
		if ( href.charAt( 0 ) === '#' ) {
			return false;
		}

		// Non-navigational schemes.
		if ( /^(mailto:|tel:|javascript:|data:)/i.test( href ) ) {
			return false;
		}

		var url;
		try {
			url = new URL( link.href, window.location.href );
		} catch ( err ) {
			return false;
		}

		// External links: leave alone.
		if ( url.hostname !== window.location.hostname ) {
			return false;
		}

		// Same page, only a fragment differs.
		if ( url.pathname === window.location.pathname && url.search === window.location.search && url.hash ) {
			return false;
		}

		// Auth & request-access URLs work directly.
		if ( /wp-login\.php/i.test( url.pathname ) || /wp-signup\.php/i.test( url.pathname ) ) {
			return false;
		}

		return true;
	}

	document.addEventListener(
		'click',
		function ( e ) {
			// Trigger buttons.
			var trigger = e.target.closest ? e.target.closest( '[data-member-view-open]' ) : null;
			if ( trigger ) {
				e.preventDefault();
				openModal();
				return;
			}

			if ( e.target.closest( '[data-member-view-close]' ) ) {
				e.preventDefault();
				closeModal();
				return;
			}

			// Click on the overlay backdrop (outside the dialog) closes.
			if ( e.target === overlay ) {
				closeModal();
				return;
			}

			// Modifier clicks / non-left clicks: let the browser handle.
			if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
				return;
			}

			var link = e.target.closest ? e.target.closest( 'a[href]' ) : null;
			if ( link && shouldIntercept( link ) ) {
				e.preventDefault();
				openModal();
			}
		},
		false
	);

	// Tab switching.
	Array.prototype.forEach.call( dialog.querySelectorAll( '[data-member-view-tab]' ), function ( tab ) {
		tab.addEventListener( 'click', function () {
			var name = tab.getAttribute( 'data-member-view-tab' );
			Array.prototype.forEach.call( dialog.querySelectorAll( '[data-member-view-tab]' ), function ( t ) {
				var active = t === tab;
				t.classList.toggle( 'is-active', active );
				t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			} );
			Array.prototype.forEach.call( dialog.querySelectorAll( '[data-member-view-panel]' ), function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-member-view-panel' ) !== name;
			} );
		} );
	} );

	var messageEl = dialog.querySelector( '[data-member-view-message]' );

	function showMessage( text, isError ) {
		if ( ! messageEl ) {
			return;
		}
		messageEl.textContent = text;
		messageEl.hidden = false;
		messageEl.classList.toggle( 'is-error', !! isError );
	}

	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	// Login form.
	var loginForm = dialog.querySelector( '[data-member-view-login]' );
	if ( loginForm ) {
		loginForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			showMessage( '', false );
			var btn = loginForm.querySelector( '[type="submit"]' );
			if ( btn ) {
				btn.disabled = true;
			}
			post( 'member_view_login', {
				nonce: cfg.loginNonce,
				log: loginForm.elements.log.value,
				pwd: loginForm.elements.pwd.value,
				rememberme: loginForm.elements.rememberme && loginForm.elements.rememberme.checked ? '1' : '',
				redirect: cfg.redirectTo || '',
			} )
				.then( function ( res ) {
					if ( res && res.success ) {
						window.location.href = ( res.data && res.data.redirect ) || cfg.origin;
					} else {
						showMessage( ( res && res.data && res.data.message ) || cfg.i18n.genericError, true );
						if ( btn ) {
							btn.disabled = false;
						}
					}
				} )
				.catch( function () {
					showMessage( cfg.i18n.genericError, true );
					if ( btn ) {
						btn.disabled = false;
					}
				} );
		} );
	}

	// Request-access form.
	var requestForm = dialog.querySelector( '[data-member-view-request]' );
	if ( requestForm ) {
		requestForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			showMessage( '', false );
			var btn = requestForm.querySelector( '[type="submit"]' );
			if ( btn ) {
				btn.disabled = true;
			}
			post( 'member_view_request_access', {
				nonce: cfg.requestNonce,
				name: requestForm.elements.name.value,
				email: requestForm.elements.email.value,
				member_view_hp: requestForm.elements.member_view_hp.value,
			} )
				.then( function ( res ) {
					if ( res && res.success ) {
						requestForm.reset();
						showMessage( ( res.data && res.data.message ) || cfg.i18n.requestSuccess, false );
					} else {
						showMessage( ( res && res.data && res.data.message ) || cfg.i18n.genericError, true );
					}
					if ( btn ) {
						btn.disabled = false;
					}
				} )
				.catch( function () {
					showMessage( cfg.i18n.genericError, true );
					if ( btn ) {
						btn.disabled = false;
					}
				} );
		} );
	}
} )();
