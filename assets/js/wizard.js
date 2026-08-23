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

	var STAR_SVG = '<svg viewBox="0 0 24 24" width="14" height="14" fill="#fff"><path d="M12 2l2.9 6.9L22 9.6l-5.5 4.8L18 22l-6-3.6L6 22l1.5-7.6L2 9.6l7.1-.7L12 2z"/></svg>';

	function tierBadge( tier, size ) {
		var svg = size <= 20
			? STAR_SVG
			: STAR_SVG.replace( 'width="14" height="14"', 'width="22" height="22"' );
		return el( 'div', {
			class: 'ssw-tier-badge' + ( size <= 20 ? ' ssw-tier-badge-sm' : '' ),
			style: 'background:' + ( ( tier && tier.badgeColor ) || '#002144' ) + ';',
			html: svg,
		} );
	}

	function SSWWizard( root ) {
		this.root = root;
		this.config = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		this.storageKey = 'ssw_wizard_' + ( root.getAttribute( 'data-widget-id' ) || 'default' );
		this.startedAt = Date.now();

		// Only a title reference is persisted for tier/template (not the full
		// object) so a restored session always resolves against the *current*
		// config shape via selectedTier()/selectedTemplate() below, instead of
		// replaying a frozen snapshot that goes stale the moment the widget's
		// settings (e.g. a new badge color or image field) change.
		this.state = {
			templateTitle: '',
			tierTitle: '',
			domain: '',
			selectedSlugs: [],
			step: 0,
		};

		this.steps = [];
		if ( this.config.enableTierStep ) this.steps.push( 'tier' );
		if ( this.config.enableTemplateStep ) this.steps.push( 'template' );
		if ( this.config.enableDomainStep ) this.steps.push( 'domain' );
		this.steps.push( 'solutions' );
		this.steps.push( 'checkout' );

		this.restore();
		// A restored session may predate a config change (steps added/removed
		// since the visitor started), so the saved step index could point
		// past the current step list — clamp it back into range.
		this.state.step = Math.max( 0, Math.min( this.state.step || 0, this.steps.length - 1 ) );
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

	SSWWizard.prototype.selectedTier = function () {
		var title = this.state.tierTitle;
		if ( ! title ) return null;
		return ( this.config.tiers || [] ).filter( function ( t ) { return t.title === title; } )[ 0 ] || null;
	};

	SSWWizard.prototype.selectedTemplate = function () {
		var title = this.state.templateTitle;
		if ( ! title ) return null;
		return ( this.config.templates || [] ).filter( function ( t ) { return t.title === title; } )[ 0 ] || null;
	};

	SSWWizard.prototype.pointsIncluded = function () {
		var tier = this.selectedTier();
		return tier ? tier.points : 0;
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
		if ( 'template' === step ) return !! this.state.templateTitle;
		if ( 'tier' === step ) return !! this.state.tierTitle;
		if ( 'domain' === step ) return !! this.state.domainSkipped || !! this.state.domain;
		return true;
	};

	/* ------------------------------------------------------------ render */

	SSWWizard.prototype.render = function () {
		this.root.innerHTML = '';
		this.root.appendChild( this.renderStepIndicator() );

		var step = this.currentStepName();
		var panel;
		if ( 'template' === step ) panel = this.renderTemplatePanel();
		else if ( 'tier' === step ) panel = this.renderTierPanel();
		else if ( 'domain' === step ) panel = this.renderDomainPanel();
		else if ( 'solutions' === step ) panel = this.renderSolutionsPanel();
		else panel = this.renderCheckoutPanel();

		this.root.appendChild( panel );
		this.root.appendChild( this.renderLightbox() );
	};

	SSWWizard.prototype.renderStepIndicator = function () {
		var wrap = el( 'div', { class: 'ssw-steps' } );
		var labels = {
			template: 'Template',
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

	/* ------------------------------------------------------------ template step */

	SSWWizard.prototype.renderTemplatePanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Pick a website template' ] ) );
		wrap.appendChild( el( 'p', {}, [ 'Click a mockup to preview it, then select the one you want.' ] ) );
		var grid = el( 'div', { class: 'ssw-grid' } );

		this.config.templates.forEach( function ( tmpl ) {
			var selected = this.state.templateTitle === tmpl.title;
			var card = el( 'div', { class: 'ssw-card' + ( selected ? ' selected' : '' ) } );
			if ( tmpl.image ) {
				var img = el( 'img', { src: tmpl.image, alt: tmpl.title } );
				img.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					this.openLightbox( tmpl.gallery && tmpl.gallery.length ? tmpl.gallery : [ tmpl.image ] );
				}.bind( this ) );
				card.appendChild( img );
			}
			card.appendChild( el( 'h4', {}, [ tmpl.title ] ) );
			card.addEventListener( 'click', function () {
				this.state.templateTitle = tmpl.title;
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );

		wrap.appendChild( grid );
		wrap.appendChild( this.renderNav() );
		return wrap;
	};

	/* ------------------------------------------------------------ tier step */

	SSWWizard.prototype.renderTierPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Choose your package' ] ) );
		var grid = el( 'div', { class: 'ssw-grid' } );

		this.config.tiers.forEach( function ( tier ) {
			var selected = this.state.tierTitle === tier.title;
			var card = el( 'div', { class: 'ssw-card ssw-tier-card' + ( selected ? ' selected' : '' ) } );
			var header = el( 'div', { class: 'ssw-tier-header' } );
			header.appendChild( tierBadge( tier, 56 ) );
			header.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ tier.points + ' pts included' ] ) );
			card.appendChild( header );
			card.appendChild( el( 'h4', {}, [ tier.title ] ) );
			if ( tier.tagline ) card.appendChild( el( 'p', { style: 'font-weight:600;' }, [ tier.tagline ] ) );
			if ( tier.description ) card.appendChild( el( 'p', {}, [ tier.description ] ) );
			card.addEventListener( 'click', function () {
				this.state.tierTitle = tier.title;
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

		if ( this.state.tierTitle ) {
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
			var tier = this.selectedTier();
			wrap.appendChild( el( 'div', { class: 'ssw-shortfall-banner' }, [
				"You're " + ( used - included ) + ' points over your ' + ( tier ? tier.title : 'package' ) + ' allowance — that\'s fine, we\'ll include it in your proposal.',
			] ) );
		}
		return wrap;
	};

	/* ------------------------------------------------------------ checkout step */

	SSWWizard.prototype.renderCheckoutPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		var layout = el( 'div', { class: 'ssw-checkout-layout' } );
		var formCol = el( 'div', { class: 'ssw-checkout-form' } );
		formCol.appendChild( el( 'h3', { class: 'ssw-heading' }, [ 'Your Details' ] ) );

		var form = el( 'form', { novalidate: 'novalidate' } );
		var fields = this.config.fields || {};
		var inputs = {};

		var nameRow = el( 'div', { class: 'ssw-field-row' } );
		form.appendChild( nameRow );
		[ [ 'firstName', 'First Name' ], [ 'lastName', 'Last Name' ] ].forEach( function ( f ) {
			var required = !! fields.name;
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ f[ 1 ] + ( required ? ' *' : '' ) ] ) );
			var input = el( 'input', { type: 'text', name: f[ 0 ] } );
			if ( required ) input.setAttribute( 'required', 'required' );
			fieldWrap.appendChild( input );
			nameRow.appendChild( fieldWrap );
			inputs[ f[ 0 ] ] = { el: input, requiredKey: 'name' };
		} );

		( function () {
			var required = !! fields.company;
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ 'Company' + ( required ? ' *' : ' (optional)' ) ] ) );
			var input = el( 'input', { type: 'text', name: 'company' } );
			if ( required ) input.setAttribute( 'required', 'required' );
			fieldWrap.appendChild( input );
			form.appendChild( fieldWrap );
			inputs.company = { el: input, requiredKey: 'company' };
		} )();

		if ( fields.address ) {
			( function () {
				var fieldWrap = el( 'div', { class: 'ssw-field' } );
				fieldWrap.appendChild( el( 'label', {}, [ 'Country / Region *' ] ) );
				var input = el( 'input', { type: 'text', name: 'country' } );
				input.setAttribute( 'required', 'required' );
				fieldWrap.appendChild( input );
				form.appendChild( fieldWrap );
				inputs.country = { el: input, requiredKey: 'address' };
			} )();

			( function () {
				var fieldWrap = el( 'div', { class: 'ssw-field' } );
				fieldWrap.appendChild( el( 'label', {}, [ 'Street Address *' ] ) );
				var input = el( 'input', { type: 'text', name: 'address1', placeholder: 'House number and street name' } );
				input.setAttribute( 'required', 'required' );
				fieldWrap.appendChild( input );
				form.appendChild( fieldWrap );
				inputs.address1 = { el: input, requiredKey: 'address' };
			} )();

			( function () {
				var fieldWrap = el( 'div', { class: 'ssw-field' } );
				fieldWrap.appendChild( el( 'label', {}, [ 'Address Line 2 (optional)' ] ) );
				var input = el( 'input', { type: 'text', name: 'address2', placeholder: 'Apartment, suite, unit, etc.' } );
				fieldWrap.appendChild( input );
				form.appendChild( fieldWrap );
				inputs.address2 = { el: input, requiredKey: null };
			} )();

			var addrRow = el( 'div', { class: 'ssw-field-row' } );
			form.appendChild( addrRow );
			[ [ 'city', 'Town / City' ], [ 'state', 'State / County' ] ].forEach( function ( f ) {
				var fieldWrap = el( 'div', { class: 'ssw-field' } );
				fieldWrap.appendChild( el( 'label', {}, [ f[ 1 ] + ' *' ] ) );
				var input = el( 'input', { type: 'text', name: f[ 0 ] } );
				input.setAttribute( 'required', 'required' );
				fieldWrap.appendChild( input );
				addrRow.appendChild( fieldWrap );
				inputs[ f[ 0 ] ] = { el: input, requiredKey: 'address' };
			} );

			( function () {
				var fieldWrap = el( 'div', { class: 'ssw-field' } );
				fieldWrap.appendChild( el( 'label', {}, [ 'Postcode / ZIP *' ] ) );
				var input = el( 'input', { type: 'text', name: 'zip' } );
				input.setAttribute( 'required', 'required' );
				fieldWrap.appendChild( input );
				form.appendChild( fieldWrap );
				inputs.zip = { el: input, requiredKey: 'address' };
			} )();
		}

		( function () {
			var required = !! fields.phone;
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ 'Phone' + ( required ? ' *' : ' (optional)' ) ] ) );
			var input = el( 'input', { type: 'tel', name: 'phone' } );
			if ( required ) input.setAttribute( 'required', 'required' );
			fieldWrap.appendChild( input );
			form.appendChild( fieldWrap );
			inputs.phone = { el: input, requiredKey: 'phone' };
		} )();

		( function () {
			var required = !! fields.email;
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ 'Email' + ( required ? ' *' : ' (optional)' ) ] ) );
			var input = el( 'input', { type: 'email', name: 'email' } );
			if ( required ) input.setAttribute( 'required', 'required' );
			fieldWrap.appendChild( input );
			form.appendChild( fieldWrap );
			inputs.email = { el: input, requiredKey: 'email' };
		} )();

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
				var input = inputs[ key ].el;
				var requiredKey = inputs[ key ].requiredKey;
				var fieldWrap = input.closest( '.ssw-field' );
				fieldWrap.classList.remove( 'error' );
				var value = input.value.trim();
				if ( requiredKey && fields[ requiredKey ] && ! value ) {
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

			var val = function ( key ) { return inputs[ key ] ? inputs[ key ].el.value.trim() : ''; };
			var fullName = ( val( 'firstName' ) + ' ' + val( 'lastName' ) ).trim();

			var payload = {
				name: fullName,
				first_name: val( 'firstName' ),
				last_name: val( 'lastName' ),
				email: val( 'email' ),
				phone: val( 'phone' ),
				company: val( 'company' ),
				country: val( 'country' ),
				address_1: val( 'address1' ),
				address_2: val( 'address2' ),
				city: val( 'city' ),
				state: val( 'state' ),
				zip: val( 'zip' ),
				tier_title: this.state.tierTitle || '',
				tier_points: this.pointsIncluded(),
				template_title: this.state.templateTitle || '',
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

		formCol.appendChild( form );
		layout.appendChild( formCol );
		layout.appendChild( this.renderOrderSummary() );
		wrap.appendChild( layout );
		return wrap;
	};

	SSWWizard.prototype.renderOrderSummary = function () {
		var box = el( 'div', { class: 'ssw-checkout-summary' } );
		box.appendChild( el( 'h4', {}, [ 'Your Order' ] ) );

		var list = el( 'div', { class: 'ssw-order-list' } );
		var rowCount = 0;

		function row( mainNodes, pointsText ) {
			var main = el( 'div', { class: 'ssw-order-row-main' }, mainNodes );
			return el( 'div', { class: 'ssw-order-row' }, [ main, el( 'div', { class: 'ssw-order-row-points' }, [ pointsText ] ) ] );
		}

		var tmpl = this.selectedTemplate();
		if ( tmpl ) {
			var mainNodes = [];
			if ( tmpl.image ) {
				var thumb = el( 'img', { class: 'ssw-order-thumb', src: tmpl.image, alt: tmpl.title } );
				thumb.addEventListener( 'click', function () {
					this.openLightbox( tmpl.gallery && tmpl.gallery.length ? tmpl.gallery : [ tmpl.image ] );
				}.bind( this ) );
				mainNodes.push( thumb );
			}
			mainNodes.push( el( 'span', {}, [ 'Website Template: ' + tmpl.title ] ) );
			list.appendChild( row( mainNodes, '—' ) );
			rowCount++;
		}

		var tier = this.selectedTier();
		if ( tier ) {
			list.appendChild( row( [ tierBadge( tier, 20 ), el( 'span', {}, [ 'Package: ' + tier.title ] ) ], String( this.pointsIncluded() ) ) );
			rowCount++;
		}

		if ( this.state.domain ) {
			list.appendChild( row( [ el( 'span', {}, [ 'Domain: ' + this.state.domain ] ) ], '—' ) );
			rowCount++;
		}

		this.selectedSolutions().forEach( function ( s ) {
			var mainNodes = [];
			if ( s.icon ) mainNodes.push( el( 'img', { class: 'ssw-order-icon', src: s.icon, alt: '' } ) );
			mainNodes.push( el( 'span', {}, [ s.title ] ) );
			list.appendChild( row( mainNodes, String( s.points ) ) );
			rowCount++;
		}.bind( this ) );

		if ( ! rowCount ) {
			list.appendChild( row( [ el( 'span', {}, [ 'Nothing selected yet.' ] ) ], '' ) );
		}
		box.appendChild( list );

		var used = this.pointsUsed();
		var included = this.pointsIncluded();

		if ( tier ) {
			var breakdown = el( 'div', { class: 'ssw-order-breakdown' } );
			breakdown.appendChild( row( [ el( 'span', {}, [ 'Points used' ] ) ], String( used ) ) );
			breakdown.appendChild( row( [ el( 'span', {}, [ tier.title + ' allowance' ] ) ], '−' + included ) );
			box.appendChild( breakdown );
		}

		var diff = used - included;
		var totalLabel = ! tier
			? used + ' pts'
			: diff > 0
				? diff + ' pts over budget'
				: ( -diff ) + ' pts remaining';
		var total = el( 'div', { class: 'ssw-order-total' + ( diff > 0 ? ' over' : '' ) }, [
			el( 'span', {}, [ 'Total' ] ),
			el( 'span', {}, [ totalLabel ] ),
		] );
		box.appendChild( total );

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
		if ( this._buildLightboxThumbs ) this._buildLightboxThumbs();
		this.showLightboxImage();
		var lb = this.root.querySelector( '.ssw-lightbox' );
		if ( lb ) lb.classList.add( 'open' );
	};

	SSWWizard.prototype.showLightboxImage = function () {
		var img = this.root.querySelector( '.ssw-lightbox img.ssw-lightbox-main' );
		if ( img && this.lightboxImages ) img.src = this.lightboxImages[ this.lightboxIndex ];

		var thumbs = this.root.querySelectorAll( '.ssw-lightbox-thumbs img' );
		thumbs.forEach( function ( thumb, i ) {
			thumb.classList.toggle( 'active', i === this.lightboxIndex );
		}.bind( this ) );
	};

	SSWWizard.prototype.renderLightbox = function () {
		var lb = el( 'div', { class: 'ssw-lightbox' } );
		var content = el( 'div', { class: 'ssw-lightbox-content' } );
		var img = el( 'img', { class: 'ssw-lightbox-main', src: '', alt: '' } );
		var thumbs = el( 'div', { class: 'ssw-lightbox-thumbs' } );
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

		// Thumbnail strip is rebuilt each time the lightbox opens (image set
		// changes per template/tier) rather than at initial render.
		this._buildLightboxThumbs = function () {
			thumbs.innerHTML = '';
			if ( ! this.lightboxImages || this.lightboxImages.length < 2 ) return;
			this.lightboxImages.forEach( function ( src, i ) {
				var thumb = el( 'img', { src: src, alt: '', class: i === this.lightboxIndex ? 'active' : '' } );
				thumb.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					this.lightboxIndex = i;
					this.showLightboxImage();
				}.bind( this ) );
				thumbs.appendChild( thumb );
			}.bind( this ) );
		}.bind( this );

		content.appendChild( img );
		content.appendChild( thumbs );
		lb.appendChild( content );
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
