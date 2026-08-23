(function () {
	'use strict';

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			if ( key === 'class' ) node.className = attrs[ key ];
			else if ( key === 'html' ) node.innerHTML = attrs[ key ];
			else if ( key.indexOf( 'on' ) === 0 && typeof attrs[ key ] === 'function' ) node.addEventListener( key.slice( 2 ), attrs[ key ] );
			else node.setAttribute( key, attrs[ key ] );
		} );
		( children || [] ).forEach( function ( child ) {
			if ( typeof child === 'string' ) node.appendChild( document.createTextNode( child ) );
			else if ( child ) node.appendChild( child );
		} );
		return node;
	}

	function SSWWizard( root ) {
		this.root = root;
		this.config = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		this.storageKey = 'ssw_wizard_' + ( root.getAttribute( 'data-widget-id' ) || 'default' );
		this.startedAt = Date.now();

		this.state = {
			tier: null,
			domain: '',
			selectedSlugs: [],
			step: 0,
		};

		this.steps = [];
		if ( this.config.enableTierStep ) this.steps.push( 'tier' );
		if ( this.config.enableDomainStep ) this.steps.push( 'domain' );
		this.steps.push( 'solutions' );
		this.steps.push( 'checkout' );

		this.restore();
		this.applyPreselect();
		this.render();
	}

	SSWWizard.prototype.persist = function () {
		try {
			sessionStorage.setItem( this.storageKey, JSON.stringify( this.state ) );
		} catch ( e ) {}
	};

	SSWWizard.prototype.restore = function () {
		try {
			var raw = sessionStorage.getItem( this.storageKey );
			if ( raw ) {
				var saved = JSON.parse( raw );
				this.state = Object.assign( this.state, saved );
				this.hadSession = true;
			}
		} catch ( e ) {}
	};

	SSWWizard.prototype.applyPreselect = function () {
		if ( this.hadSession ) return;
		var param = this.config.preselectParam || 'solution';
		var url = new URL( window.location.href );
		var slug = url.searchParams.get( param );
		if ( slug && this.config.solutions.some( function ( s ) { return s.slug === slug; } ) ) {
			this.state.selectedSlugs.push( slug );
		} else {
			this.config.solutions.forEach( function ( s ) {
				if ( s.checked ) this.state.selectedSlugs.push( s.slug );
			}.bind( this ) );
		}
	};

	SSWWizard.prototype.pointsIncluded = function () {
		return this.state.tier ? this.state.tier.points : 0;
	};

	SSWWizard.prototype.pointsUsed = function () {
		var total = 0;
		this.selectedSolutions().forEach( function ( s ) { total += s.points; } );
		return total;
	};

	SSWWizard.prototype.selectedSolutions = function () {
		var slugs = this.state.selectedSlugs;
		return this.config.solutions.filter( function ( s ) { return slugs.indexOf( s.slug ) !== -1; } );
	};

	SSWWizard.prototype.currentStepName = function () {
		return this.steps[ this.state.step ];
	};

	SSWWizard.prototype.goTo = function ( index ) {
		this.state.step = Math.max( 0, Math.min( this.steps.length - 1, index ) );
		this.persist();
		this.render();
	};

	SSWWizard.prototype.canAdvance = function () {
		var step = this.currentStepName();
		if ( 'tier' === step ) return !! this.state.tier;
		if ( 'domain' === step ) return !! this.state.domainSkipped || !! this.state.domain;
		return true;
	};

	/* ------------------------------------------------------------ render */

	SSWWizard.prototype.render = function () {
		this.root.innerHTML = '';
		this.root.appendChild( this.renderStepIndicator() );

		var step = this.currentStepName();
		var panel;
		if ( 'tier' === step ) panel = this.renderTierPanel();
		else if ( 'domain' === step ) panel = this.renderDomainPanel();
		else if ( 'solutions' === step ) panel = this.renderSolutionsPanel();
		else panel = this.renderCheckoutPanel();

		this.root.appendChild( panel );
		this.root.appendChild( this.renderLightbox() );
	};

	SSWWizard.prototype.renderStepIndicator = function () {
		var wrap = el( 'div', { class: 'ssw-steps' } );
		var labels = {
			tier: 'Package',
			domain: 'Domain',
			solutions: 'Solutions',
			checkout: 'Checkout',
		};
		this.steps.forEach( function ( name, i ) {
			var cls = 'ssw-step-dot';
			if ( i < this.state.step ) cls += ' done';
			if ( i === this.state.step ) cls += ' current';
			wrap.appendChild( el( 'div', { class: cls }, [
				el( 'span', { class: 'num' }, [ String( i + 1 ) ] ),
				el( 'span', {}, [ labels[ name ] ] ),
			] ) );
			if ( i < this.steps.length - 1 ) wrap.appendChild( el( 'div', { class: 'ssw-step-line' } ) );
		}.bind( this ) );
		return wrap;
	};

	SSWWizard.prototype.renderNav = function ( opts ) {
		opts = opts || {};
		var nav = el( 'div', { class: 'ssw-nav' } );
		var back = el( 'button', { class: 'ssw-btn ghost', type: 'button' }, [ 'Back' ] );
		back.disabled = this.state.step === 0;
		back.addEventListener( 'click', function () { this.goTo( this.state.step - 1 ); }.bind( this ) );

		var rightWrap = el( 'div', {} );
		if ( opts.skippable ) {
			var skip = el( 'button', { class: 'ssw-btn ghost', type: 'button', style: 'margin-right:8px;' }, [ 'Skip this step' ] );
			skip.addEventListener( 'click', function () {
				this.state.domainSkipped = true;
				this.state.domain = '';
				this.goTo( this.state.step + 1 );
			}.bind( this ) );
			rightWrap.appendChild( skip );
		}

		var next = el( 'button', { class: 'ssw-btn', type: 'button' }, [ opts.nextLabel || 'Next' ] );
		next.disabled = ! this.canAdvance();
		next.addEventListener( 'click', function () {
			if ( ! this.canAdvance() ) return;
			if ( opts.onNext ) opts.onNext();
			else this.goTo( this.state.step + 1 );
		}.bind( this ) );
		rightWrap.appendChild( next );

		nav.appendChild( back );
		nav.appendChild( rightWrap );
		return nav;
	};

	/* ------------------------------------------------------------ tier step */

	SSWWizard.prototype.renderTierPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Choose your package' ] ) );
		var grid = el( 'div', { class: 'ssw-grid' } );

		this.config.tiers.forEach( function ( tier ) {
			var selected = this.state.tier && this.state.tier.title === tier.title;
			var card = el( 'div', { class: 'ssw-card' + ( selected ? ' selected' : '' ) } );
			if ( tier.image ) {
				var img = el( 'img', { src: tier.image, alt: tier.title } );
				img.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					this.openLightbox( tier.gallery && tier.gallery.length ? tier.gallery : [ tier.image ] );
				}.bind( this ) );
				card.appendChild( img );
			}
			card.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ tier.points + ' points included' ] ) );
			card.appendChild( el( 'h4', {}, [ tier.title ] ) );
			if ( tier.tagline ) card.appendChild( el( 'p', { style: 'font-weight:600;' }, [ tier.tagline ] ) );
			if ( tier.description ) card.appendChild( el( 'p', {}, [ tier.description ] ) );
			card.addEventListener( 'click', function () {
				this.state.tier = tier;
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );

		wrap.appendChild( grid );
		wrap.appendChild( this.renderNav() );
		return wrap;
	};

	/* ------------------------------------------------------------ domain step */

	SSWWizard.prototype.renderDomainPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.domainHeading || "Let's find your domain" ] ) );

		var row = el( 'div', { class: 'ssw-domain-row' } );
		var input = el( 'input', { class: 'ssw-input', type: 'text', placeholder: 'yourbusiness.com', value: this.state.domainQuery || '' } );
		var checkBtn = el( 'button', { class: 'ssw-btn', type: 'button' }, [ 'Check availability' ] );
		row.appendChild( input );
		row.appendChild( checkBtn );
		wrap.appendChild( row );

		var results = el( 'div', { class: 'ssw-domain-results' } );
		wrap.appendChild( results );

		var doCheck = function () {
			var q = input.value.trim();
			if ( ! q ) return;
			this.state.domainQuery = q;
			results.innerHTML = '';
			results.appendChild( el( 'p', {}, [ 'Checking…' ] ) );

			fetch( this.config.restUrl + '/domain-search?q=' + encodeURIComponent( q ), {
				headers: { 'X-WP-Nonce': this.config.nonce },
			} )
				.then( function ( r ) { return r.json().then( function ( data ) { return { ok: r.ok, data: data }; } ); } )
				.then( function ( res ) {
					var data = res.data;
					results.innerHTML = '';
					if ( ! res.ok || data.error ) {
						if ( ! data.error ) data.error = 'Domain search is temporarily unavailable.';
						results.appendChild( el( 'div', { class: 'ssw-domain-result error' }, [ data.error ] ) );
						return;
					}
					results.appendChild(
						el( 'div', { class: 'ssw-domain-result ' + ( data.available ? 'available' : 'taken' ) }, [
							q + ( data.available ? ' is available!' : ' is already taken.' ),
						] )
					);
					if ( data.available ) {
						var pick = el( 'div', { class: 'ssw-suggestion selected' }, [
							el( 'span', {}, [ q ] ),
							el( 'span', { class: 'status available' }, [ 'Selected' ] ),
						] );
						results.appendChild( pick );
						this.state.domain = q;
						this.state.domainSkipped = false;
						this.persist();
						this.updateNextButton();
					}
					( data.suggestions || [] ).forEach( function ( sug ) {
						if ( ! sug.available ) return;
						var row2 = el( 'div', { class: 'ssw-suggestion' + ( this.state.domain === sug.domain ? ' selected' : '' ) }, [
							el( 'span', {}, [ sug.domain ] ),
							el( 'span', { class: 'status available' }, [ 'Available' ] ),
						] );
						row2.addEventListener( 'click', function () {
							this.state.domain = sug.domain;
							this.state.domainSkipped = false;
							this.persist();
							this.render();
						}.bind( this ) );
						results.appendChild( row2 );
					}.bind( this ) );
				}.bind( this ) )
				.catch( function () {
					results.innerHTML = '';
					results.appendChild( el( 'div', { class: 'ssw-domain-result error' }, [ 'Domain search is temporarily unavailable.' ] ) );
				} );
		}.bind( this );

		checkBtn.addEventListener( 'click', doCheck );
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { e.preventDefault(); doCheck(); } } );

		wrap.appendChild( this.renderNav( { skippable: true } ) );
		return wrap;
	};

	SSWWizard.prototype.updateNextButton = function () {
		var btn = this.root.querySelector( '.ssw-nav .ssw-btn:not(.ghost)' );
		if ( btn ) btn.disabled = ! this.canAdvance();
	};

	/* ------------------------------------------------------------ solutions step */

	SSWWizard.prototype.renderSolutionsPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Pick your solutions' ] ) );

		if ( this.state.tier ) {
			wrap.appendChild( this.renderPointsBar() );
		}

		var grid = el( 'div', { class: 'ssw-grid' } );
		this.config.solutions.forEach( function ( sol ) {
			var selected = this.state.selectedSlugs.indexOf( sol.slug ) !== -1;
			var card = el( 'div', { class: 'ssw-card' + ( selected ? ' selected' : '' ) } );
			if ( sol.icon ) card.appendChild( el( 'img', { class: 'ssw-card-icon', src: sol.icon, alt: '' } ) );
			card.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ sol.points + ' pts' ] ) );
			card.appendChild( el( 'h4', {}, [ sol.title ] ) );
			if ( sol.description ) card.appendChild( el( 'p', {}, [ sol.description ] ) );
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedSlugs.indexOf( sol.slug );
				if ( idx === -1 ) this.state.selectedSlugs.push( sol.slug );
				else this.state.selectedSlugs.splice( idx, 1 );
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );

		wrap.appendChild( grid );
		wrap.appendChild( this.renderNav() );
		return wrap;
	};

	SSWWizard.prototype.renderPointsBar = function () {
		var included = this.pointsIncluded();
		var used = this.pointsUsed();
		var pct = included > 0 ? Math.min( 100, ( used / included ) * 100 ) : 0;
		var over = used > included;

		var wrap = el( 'div', { class: 'ssw-points-bar-wrap' } );
		var bar = el( 'div', { class: 'ssw-points-bar' } );
		bar.appendChild( el( 'div', { class: 'ssw-points-bar-fill' + ( over ? ' over' : ''), style: 'width:' + pct + '%;' } ) );
		wrap.appendChild( bar );
		wrap.appendChild( el( 'div', { class: 'ssw-points-label' }, [ used + ' / ' + included + ' points used' ] ) );
		if ( over ) {
			wrap.appendChild( el( 'div', { class: 'ssw-shortfall-banner' }, [
				"You're " + ( used - included ) + ' points over your ' + this.state.tier.title + ' allowance — that\'s fine, we\'ll include it in your proposal.',
			] ) );
		}
		return wrap;
	};

	/* ------------------------------------------------------------ checkout step */

	SSWWizard.prototype.renderCheckoutPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Your details' ] ) );

		wrap.appendChild( this.renderSummary() );

		var form = el( 'form', { novalidate: 'novalidate' } );
		var fields = this.config.fields || {};
		var inputs = {};

		[ [ 'name', 'Name', 'text' ], [ 'email', 'Email', 'email' ], [ 'phone', 'Phone', 'tel' ], [ 'company', 'Company', 'text' ] ].forEach( function ( f ) {
			var key = f[ 0 ], label = f[ 1 ], type = f[ 2 ];
			var required = !! fields[ key ];
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ label + ( required ? ' *' : '' ) ] ) );
			var input = el( 'input', { type: type, name: key } );
			if ( required ) input.setAttribute( 'required', 'required' );
			fieldWrap.appendChild( input );
			form.appendChild( fieldWrap );
			inputs[ key ] = input;
		} );

		var hp = el( 'input', { class: 'ssw-hp', type: 'text', name: 'hp', tabindex: '-1', autocomplete: 'off' } );
		form.appendChild( hp );

		var errorBox = el( 'div', { class: 'ssw-form-error' } );

		var nav = el( 'div', { class: 'ssw-nav' } );
		var back = el( 'button', { class: 'ssw-btn ghost', type: 'button' }, [ 'Back' ] );
		back.addEventListener( 'click', function () { this.goTo( this.state.step - 1 ); }.bind( this ) );
		var submit = el( 'button', { class: 'ssw-btn', type: 'submit' }, [ 'Submit' ] );
		nav.appendChild( back );
		nav.appendChild( submit );
		form.appendChild( errorBox );
		form.appendChild( nav );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			errorBox.textContent = '';
			var valid = true;
			Object.keys( inputs ).forEach( function ( key ) {
				var input = inputs[ key ];
				var fieldWrap = input.closest( '.ssw-field' );
				fieldWrap.classList.remove( 'error' );
				var value = input.value.trim();
				if ( fields[ key ] && ! value ) {
					valid = false;
					fieldWrap.classList.add( 'error' );
				}
				if ( key === 'email' && value && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
					valid = false;
					fieldWrap.classList.add( 'error' );
				}
			} );
			if ( ! valid ) {
				errorBox.textContent = 'Please check the highlighted fields.';
				return;
			}

			submit.disabled = true;
			submit.textContent = 'Submitting…';

			var payload = {
				name: inputs.name.value.trim(),
				email: inputs.email.value.trim(),
				phone: inputs.phone.value.trim(),
				company: inputs.company.value.trim(),
				tier_title: this.state.tier ? this.state.tier.title : '',
				tier_points: this.pointsIncluded(),
				template_title: this.state.tier ? this.state.tier.title : '',
				domain: this.state.domain || '',
				solutions: this.selectedSolutions().map( function ( s ) { return { title: s.title, points: s.points }; } ),
				page_url: this.config.pageUrl || window.location.href,
				hp: hp.value,
				started_at: this.startedAt,
			};

			fetch( this.config.restUrl + '/submit', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
				body: JSON.stringify( payload ),
			} )
				.then( function ( r ) { return r.json().then( function ( data ) { return { ok: r.ok, data: data }; } ); } )
				.then( function ( res ) {
					if ( ! res.ok || ! res.data.success ) {
						throw new Error( ( res.data && res.data.message ) || 'Something went wrong. Please try again.' );
					}
					try { sessionStorage.removeItem( this.storageKey ); } catch ( e ) {}
					this.renderThankYou();
				}.bind( this ) )
				.catch( function ( err ) {
					submit.disabled = false;
					submit.textContent = 'Submit';
					errorBox.textContent = err.message || 'Something went wrong. Please try again.';
				} );
		}.bind( this ) );

		wrap.appendChild( form );
		return wrap;
	};

	SSWWizard.prototype.renderSummary = function () {
		var box = el( 'div', { class: 'ssw-summary' } );
		box.appendChild( el( 'h4', {}, [ 'Your selection' ] ) );
		var list = el( 'ul' );
		if ( this.state.tier ) list.appendChild( el( 'li', {}, [ 'Package: ' + this.state.tier.title + ' (' + this.pointsIncluded() + ' pts)' ] ) );
		if ( this.state.domain ) list.appendChild( el( 'li', {}, [ 'Domain: ' + this.state.domain ] ) );
		this.selectedSolutions().forEach( function ( s ) {
			list.appendChild( el( 'li', {}, [ s.title + ' (' + s.points + ' pts)' ] ) );
		} );
		if ( ! list.children.length ) list.appendChild( el( 'li', {}, [ 'Nothing selected yet.' ] ) );
		box.appendChild( list );
		return box;
	};

	SSWWizard.prototype.renderThankYou = function () {
		this.root.innerHTML = '';
		this.root.appendChild(
			el( 'div', { class: 'ssw-thankyou' }, [
				el( 'h3', {}, [ "You're all set!" ] ),
				el( 'p', {}, [ this.config.successMessage || 'Thanks — our team will be in touch shortly.' ] ),
			] )
		);
	};

	/* ------------------------------------------------------------ lightbox */

	SSWWizard.prototype.openLightbox = function ( images ) {
		this.lightboxImages = images;
		this.lightboxIndex = 0;
		this.showLightboxImage();
		var lb = this.root.querySelector( '.ssw-lightbox' );
		if ( lb ) lb.classList.add( 'open' );
	};

	SSWWizard.prototype.showLightboxImage = function () {
		var img = this.root.querySelector( '.ssw-lightbox img' );
		if ( img && this.lightboxImages ) img.src = this.lightboxImages[ this.lightboxIndex ];
	};

	SSWWizard.prototype.renderLightbox = function () {
		var lb = el( 'div', { class: 'ssw-lightbox' } );
		var img = el( 'img', { src: '', alt: '' } );
		var close = el( 'button', { class: 'ssw-lightbox-close', type: 'button' }, [ '×' ] );
		var prev = el( 'button', { class: 'ssw-lightbox-prev', type: 'button' }, [ '‹' ] );
		var next = el( 'button', { class: 'ssw-lightbox-next', type: 'button' }, [ '›' ] );

		close.addEventListener( 'click', function () { lb.classList.remove( 'open' ); } );
		lb.addEventListener( 'click', function ( e ) { if ( e.target === lb ) lb.classList.remove( 'open' ); } );
		prev.addEventListener( 'click', function () {
			if ( ! this.lightboxImages ) return;
			this.lightboxIndex = ( this.lightboxIndex - 1 + this.lightboxImages.length ) % this.lightboxImages.length;
			this.showLightboxImage();
		}.bind( this ) );
		next.addEventListener( 'click', function () {
			if ( ! this.lightboxImages ) return;
			this.lightboxIndex = ( this.lightboxIndex + 1 ) % this.lightboxImages.length;
			this.showLightboxImage();
		}.bind( this ) );

		lb.appendChild( img );
		lb.appendChild( close );
		lb.appendChild( prev );
		lb.appendChild( next );
		return lb;
	};

	function initWizardsIn( scope ) {
		( scope || document ).querySelectorAll( '.ssw-wizard[data-config]:not([data-ssw-ready])' ).forEach( function ( root ) {
			root.setAttribute( 'data-ssw-ready', '1' );
			new SSWWizard( root );
		} );
	}

	// Elementor renders widgets dynamically (editor preview, popups, AJAX
	// content) after the page's initial DOMContentLoaded already fired, so
	// that event alone would miss them — hook Elementor's own per-widget
	// ready event as the primary trigger, with DOMContentLoaded as a plain
	// front-end fallback for when Elementor's frontend JS isn't present.
	document.addEventListener( 'DOMContentLoaded', function () {
		initWizardsIn( document );
	} );
	if ( 'loading' !== document.readyState ) {
		initWizardsIn( document );
	}

	function hookElementor() {
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/ssw-solutions-wizard.default', function ( $scope ) {
			initWizardsIn( $scope[ 0 ] );
		} );
	}
	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		hookElementor();
	} else {
		window.addEventListener( 'elementor/frontend/init', hookElementor );
	}
} )();
