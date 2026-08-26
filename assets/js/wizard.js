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
	var DOMAIN_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9s1.3-6.5 3.8-9z"/></svg>';
	var CHECK_CIRCLE_SVG = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>';
	var X_CIRCLE_SVG = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>';

	// Common calling codes for the checkout phone field — not exhaustive,
	// covers the markets Strivre is realistically getting leads from.
	var PHONE_CODES = [
		[ '+1', 'US/CA +1' ], [ '+44', 'UK +44' ], [ '+61', 'AU +61' ], [ '+64', 'NZ +64' ],
		[ '+63', 'PH +63' ], [ '+65', 'SG +65' ], [ '+60', 'MY +60' ], [ '+66', 'TH +66' ],
		[ '+62', 'ID +62' ], [ '+91', 'IN +91' ], [ '+971', 'AE +971' ], [ '+966', 'SA +966' ],
		[ '+27', 'ZA +27' ], [ '+353', 'IE +353' ], [ '+49', 'DE +49' ], [ '+33', 'FR +33' ],
		[ '+34', 'ES +34' ], [ '+39', 'IT +39' ], [ '+31', 'NL +31' ], [ '+46', 'SE +46' ],
		[ '+81', 'JP +81' ], [ '+82', 'KR +82' ], [ '+86', 'CN +86' ], [ '+852', 'HK +852' ],
		[ '+886', 'TW +886' ], [ '+52', 'MX +52' ], [ '+55', 'BR +55' ],
	];

	function money( n ) {
		n = Number( n ) || 0;
		return '$' + n.toLocaleString();
	}

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
			// single-page builder ("Build Your Business") additions —
			// harmless/unused in classic mode.
			marketingTitle: '',
			selectedLicenseSlugs: [],
			measureTitle: '',
			selectedMeasureAddonSlugs: [],
			domainWanted: null,
			domainName: '',
			bespokeInterested: false,
			bespokeNotes: '',
			enterpriseSelected: false,
		};

		this.steps = [];
		if ( 'single_page' === this.config.builderMode ) {
			this.steps.push( 'choices' );
		} else {
			if ( this.config.enableTierStep ) this.steps.push( 'tier' );
			if ( this.config.enableTemplateStep ) this.steps.push( 'template' );
			if ( this.config.enableDomainStep ) this.steps.push( 'domain' );
			this.steps.push( 'solutions' );
		}
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
		var list = this.modulesList();
		if ( slug && list.some( function ( s ) { return s.slug === slug; } ) ) {
			this.state.selectedSlugs.push( slug );
		} else {
			list.forEach( function ( s ) {
				if ( s.checked ) this.state.selectedSlugs.push( s.slug );
			}.bind( this ) );
		}
	};

	SSWWizard.prototype.isSinglePage = function () {
		return 'single_page' === this.config.builderMode;
	};

	// Website Package tiers and Website Modules behave identically between
	// modes (single-select tier w/ points budget; multi-select points-costed
	// catalog) — only the source list differs, so the rest of the points
	// machinery (pointsIncluded/pointsUsed/selectedSolutions/order summary)
	// stays shared by resolving the list through these two functions.
	SSWWizard.prototype.tiersList = function () {
		return ( this.isSinglePage() ? ( this.config.catalog || {} ).tiers : this.config.tiers ) || [];
	};

	SSWWizard.prototype.modulesList = function () {
		return ( this.isSinglePage() ? ( this.config.catalog || {} ).modules : this.config.solutions ) || [];
	};

	SSWWizard.prototype.selectedTier = function () {
		var title = this.state.tierTitle;
		if ( ! title ) return null;
		return this.tiersList().filter( function ( t ) { return t.title === title; } )[ 0 ] || null;
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
		return this.modulesList().filter( function ( s ) { return slugs.indexOf( s.slug ) !== -1; } );
	};

	/* -------------------------------------------- single-page builder resolvers */

	SSWWizard.prototype.selectedMarketing = function () {
		var title = this.state.marketingTitle;
		if ( ! title || ! this.config.catalog ) return null;
		return this.config.catalog.marketing.filter( function ( m ) { return m.title === title; } )[ 0 ] || null;
	};

	SSWWizard.prototype.selectedLicenses = function () {
		if ( ! this.config.catalog ) return [];
		var slugs = this.state.selectedLicenseSlugs;
		return this.config.catalog.licenses.filter( function ( l ) { return slugs.indexOf( l.slug ) !== -1; } );
	};

	SSWWizard.prototype.selectedMeasureTier = function () {
		var title = this.state.measureTitle;
		if ( ! title || ! this.config.catalog ) return null;
		return this.config.catalog.measureTiers.filter( function ( m ) { return m.title === title; } )[ 0 ] || null;
	};

	SSWWizard.prototype.selectedMeasureAddons = function () {
		if ( ! this.config.catalog ) return [];
		var slugs = this.state.selectedMeasureAddonSlugs;
		return this.config.catalog.measureAddons.filter( function ( a ) { return slugs.indexOf( a.slug ) !== -1; } );
	};

	/** Real-USD running total across every priced section (points-based
	 *  Website Package/Modules are excluded — those stay points-only). */
	SSWWizard.prototype.usdTotal = function () {
		var total = 0;
		var marketing = this.selectedMarketing();
		if ( marketing ) total += marketing.price;
		this.selectedLicenses().forEach( function ( l ) { total += l.price; } );
		var measure = this.selectedMeasureTier();
		if ( measure ) total += measure.price;
		this.selectedMeasureAddons().forEach( function ( a ) { total += a.price; } );
		return total;
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
		// Choices: only the Website Package is a real gate — everything else
		// (domain question, modules, marketing, licenses, measure, bespoke,
		// enterprise) is optional and never blocks moving on, same
		// non-blocking philosophy as the points shortfall banner.
		if ( 'choices' === step ) return !! this.state.tierTitle;
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
		else if ( 'choices' === step ) panel = this.renderChoicesPanel();
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
			choices: 'Choices',
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
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.templateHeading || 'Pick a website template' ] ) );
		wrap.appendChild( el( 'p', {}, [ this.config.templateSubheading || 'Click a mockup to preview it, then select the one you want.' ] ) );
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
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.tierHeading || 'Choose your package' ] ) );
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

	function domainRow( domainText, statusText, selected ) {
		var wrap = el( 'div', { class: 'ssw-suggestion' + ( selected ? ' selected' : '' ) } );
		wrap.appendChild( el( 'div', { class: 'ssw-suggestion-main' }, [
			el( 'span', { class: 'ssw-suggestion-icon', html: DOMAIN_SVG } ),
			el( 'span', {}, [ domainText ] ),
		] ) );
		wrap.appendChild( el( 'span', { class: 'status available' }, [
			el( 'span', { class: 'status-icon', html: CHECK_CIRCLE_SVG } ),
			el( 'span', {}, [ statusText ] ),
		] ) );
		return wrap;
	}

	SSWWizard.prototype.renderDomainPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.domainHeading || "Let's find your domain" ] ) );

		var searchBar = el( 'div', { class: 'ssw-domain-search' } );
		searchBar.appendChild( el( 'span', { class: 'ssw-domain-search-icon', html: DOMAIN_SVG } ) );
		var input = el( 'input', { type: 'text', placeholder: 'yourbusiness.com', value: this.state.domainQuery || '' } );
		var checkBtn = el( 'button', { class: 'ssw-btn', type: 'button' }, [ 'Check availability' ] );
		searchBar.appendChild( input );
		searchBar.appendChild( checkBtn );
		wrap.appendChild( searchBar );

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
						results.appendChild( el( 'div', { class: 'ssw-domain-result error' }, [
							el( 'span', { html: X_CIRCLE_SVG } ),
							el( 'span', {}, [ data.error ] ),
						] ) );
						return;
					}
					results.appendChild(
						el( 'div', { class: 'ssw-domain-result ' + ( data.available ? 'available' : 'taken' ) }, [
							el( 'span', { html: data.available ? CHECK_CIRCLE_SVG : X_CIRCLE_SVG } ),
							el( 'span', {}, [ q + ( data.available ? ' is available!' : ' is already taken.' ) ] ),
						] )
					);
					if ( data.available ) {
						results.appendChild( domainRow( q, 'Selected', true ) );
						this.state.domain = q;
						this.state.domainSkipped = false;
						this.persist();
						this.updateNextButton();
					}
					( data.suggestions || [] ).forEach( function ( sug ) {
						if ( ! sug.available ) return;
						var row2 = domainRow( sug.domain, 'Available', this.state.domain === sug.domain );
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
					results.appendChild( el( 'div', { class: 'ssw-domain-result error' }, [
						el( 'span', { html: X_CIRCLE_SVG } ),
						el( 'span', {}, [ 'Domain search is temporarily unavailable.' ] ),
					] ) );
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

	/* ------------------------------------------------------------ single-page builder ("Build Your Business") */

	SSWWizard.prototype.renderChoicesPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.headings.choices || "Let's build your business" ] ) );

		wrap.appendChild( this.renderChoicesPackageSection() );
		if ( this.config.enableChoicesTemplates ) wrap.appendChild( this.renderChoicesTemplateSection() );
		wrap.appendChild( this.renderChoicesDomainSection() );
		wrap.appendChild( this.renderChoicesModulesSection() );
		wrap.appendChild( this.renderChoicesMarketingSection() );
		wrap.appendChild( this.renderChoicesLicensesSection() );
		wrap.appendChild( this.renderChoicesMeasureSection() );
		wrap.appendChild( this.renderChoicesBespokeSection() );
		wrap.appendChild( this.renderChoicesEnterpriseSection() );

		wrap.appendChild( this.renderNav() );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesPackageSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.tiersSection || 'Website Package' ] ) );

		var locked = this.state.enterpriseSelected;
		var grid = el( 'div', { class: 'ssw-grid' + ( locked ? ' ssw-grid-locked' : '' ) } );
		( this.config.catalog.tiers || [] ).forEach( function ( tier ) {
			var selected = this.state.tierTitle === tier.title;
			var card = el( 'div', { class: 'ssw-card ssw-tier-card' + ( selected ? ' selected' : '' ) } );
			card.appendChild( el( 'div', { class: 'ssw-tier-band', style: 'background:' + ( tier.badgeColor || '#002144' ) + ';' } ) );
			var header = el( 'div', { class: 'ssw-tier-header' } );
			header.appendChild( tierBadge( tier, 56 ) );
			var badges = el( 'div', { style: 'display:flex;flex-direction:column;align-items:flex-end;gap:4px;' } );
			badges.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ tier.points + ' pts included' ] ) );
			badges.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( tier.price ) + '/mo' ] ) );
			header.appendChild( badges );
			card.appendChild( header );
			card.appendChild( el( 'h4', {}, [ tier.title ] ) );
			if ( tier.pagesNote ) card.appendChild( el( 'p', {}, [ tier.pagesNote ] ) );
			if ( selected && locked ) card.appendChild( el( 'div', { class: 'ssw-card-locked-note' }, [ 'Included in your Enterprise Plan' ] ) );
			card.addEventListener( 'click', function () {
				if ( this.state.enterpriseSelected ) return;
				this.state.tierTitle = tier.title;
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesTemplateSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.templateHeading || 'Pick a website template' ] ) );
		wrap.appendChild( el( 'p', {}, [ this.config.templateSubheading || 'Click a mockup to preview it, then select the one you want.' ] ) );

		var grid = el( 'div', { class: 'ssw-grid' } );
		( this.config.templates || [] ).forEach( function ( tmpl ) {
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
		return wrap;
	};

	SSWWizard.prototype.renderChoicesDomainSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		var heading = el( 'h4', {} );
		heading.appendChild( el( 'span', { class: 'ssw-heading-icon', html: DOMAIN_SVG } ) );
		heading.appendChild( document.createTextNode( this.config.headings.domainQuestion || 'Do you need to buy a domain?' ) );
		wrap.appendChild( heading );

		var toggle = el( 'div', { class: 'ssw-domain-toggle' } );
		[ true, false ].forEach( function ( val ) {
			var btn = el( 'button', { type: 'button', class: 'ssw-toggle-btn' + ( this.state.domainWanted === val ? ' active' : '' ) }, [ val ? 'Yes' : 'No' ] );
			btn.addEventListener( 'click', function () {
				this.state.domainWanted = val;
				if ( ! val ) this.state.domainName = '';
				this.persist();
				this.render();
			}.bind( this ) );
			toggle.appendChild( btn );
		}.bind( this ) );
		wrap.appendChild( toggle );

		if ( this.state.domainWanted ) {
			var fieldWrap = el( 'div', { class: 'ssw-field', style: 'margin-top:12px;max-width:360px;' } );
			fieldWrap.appendChild( el( 'label', {}, [ 'What domain name do you have in mind?' ] ) );
			var input = el( 'input', { type: 'text', placeholder: 'yourbusiness.com' } );
			input.value = this.state.domainName || '';
			input.addEventListener( 'input', function () { this.state.domainName = input.value; this.persist(); }.bind( this ) );
			fieldWrap.appendChild( input );
			wrap.appendChild( fieldWrap );
		}
		return wrap;
	};

	SSWWizard.prototype.renderChoicesModulesSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.modulesSection || 'Website Modules' ] ) );
		if ( this.state.tierTitle ) wrap.appendChild( this.renderPointsBar() );

		var grid = el( 'div', { class: 'ssw-grid' } );
		( this.config.catalog.modules || [] ).forEach( function ( mod ) {
			var selected = this.state.selectedSlugs.indexOf( mod.slug ) !== -1;
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var header = el( 'div', { class: 'ssw-solution-header', style: mod.icon ? '' : 'justify-content:flex-end;' } );
			if ( mod.icon ) header.appendChild( el( 'img', { class: 'ssw-card-icon', src: mod.icon, alt: '' } ) );
			var badges = el( 'div', { style: 'display:flex;flex-direction:column;align-items:flex-end;gap:4px;' } );
			badges.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ mod.points + ' pts' ] ) );
			badges.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( mod.price ) ] ) );
			header.appendChild( badges );
			card.appendChild( header );
			card.appendChild( el( 'h4', {}, [ mod.title ] ) );
			if ( mod.unitNote ) card.appendChild( el( 'p', {}, [ mod.unitNote ] ) );
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedSlugs.indexOf( mod.slug );
				if ( idx === -1 ) this.state.selectedSlugs.push( mod.slug );
				else this.state.selectedSlugs.splice( idx, 1 );
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesMarketingSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.marketingSection || 'Marketing' ] ) );

		var locked = this.state.enterpriseSelected;
		var grid = el( 'div', { class: 'ssw-grid' + ( locked ? ' ssw-grid-locked' : '' ) } );
		( this.config.catalog.marketing || [] ).forEach( function ( m ) {
			var selected = this.state.marketingTitle === m.title;
			var card = el( 'div', { class: 'ssw-card ssw-tier-card' + ( selected ? ' selected' : '' ) } );
			card.appendChild( el( 'div', { class: 'ssw-tier-band', style: 'background:' + ( m.badgeColor || '#002144' ) + ';' } ) );
			var header = el( 'div', { class: 'ssw-tier-header' } );
			header.appendChild( tierBadge( m, 40 ) );
			header.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( m.price ) + '/mo/brand' ] ) );
			card.appendChild( header );
			card.appendChild( el( 'h4', {}, [ m.title ] ) );
			var list = el( 'ul', { class: 'ssw-feature-list' } );
			( m.features || [] ).forEach( function ( f ) { list.appendChild( el( 'li', {}, [ f ] ) ); } );
			card.appendChild( list );
			if ( selected && locked ) card.appendChild( el( 'div', { class: 'ssw-card-locked-note' }, [ 'Included in your Enterprise Plan' ] ) );
			card.addEventListener( 'click', function () {
				if ( this.state.enterpriseSelected ) return;
				this.state.marketingTitle = m.title;
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesLicensesSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.licensesSection || 'Licenses' ] ) );

		var grid = el( 'div', { class: 'ssw-grid' } );
		( this.config.catalog.licenses || [] ).forEach( function ( lic ) {
			var selected = this.state.selectedLicenseSlugs.indexOf( lic.slug ) !== -1;
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var licHeader = el( 'div', { class: 'ssw-solution-header', style: lic.icon ? '' : 'justify-content:flex-end;' } );
			if ( lic.icon ) licHeader.appendChild( el( 'img', { class: 'ssw-card-icon', src: lic.icon, alt: '' } ) );
			licHeader.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( lic.price ) + '/mo' ] ) );
			card.appendChild( licHeader );
			card.appendChild( el( 'h4', {}, [ lic.title ] ) );
			if ( lic.unitNote ) card.appendChild( el( 'p', {}, [ lic.unitNote ] ) );
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedLicenseSlugs.indexOf( lic.slug );
				if ( idx === -1 ) this.state.selectedLicenseSlugs.push( lic.slug );
				else this.state.selectedLicenseSlugs.splice( idx, 1 );
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesMeasureSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.measureSection || 'Measure Analytics' ] ) );

		var locked = this.state.enterpriseSelected;
		var grid = el( 'div', { class: 'ssw-grid-fixed3' + ( locked ? ' ssw-grid-locked' : '' ) } );
		( this.config.catalog.measureTiers || [] ).forEach( function ( m ) {
			var selected = this.state.measureTitle === m.title;
			var card = el( 'div', { class: 'ssw-card ssw-tier-card' + ( selected ? ' selected' : '' ) } );
			var mHeader = el( 'div', { style: 'display:flex;align-items:center;justify-content:' + ( m.icon ? 'space-between' : 'flex-end' ) + ';margin-bottom:8px;' } );
			if ( m.icon ) mHeader.appendChild( el( 'img', { class: 'ssw-card-icon', style: 'margin-bottom:0;', src: m.icon, alt: '' } ) );
			mHeader.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( m.price ) + '/mo' ] ) );
			card.appendChild( mHeader );
			card.appendChild( el( 'h4', {}, [ m.title ] ) );
			card.appendChild( el( 'p', { style: 'font-weight:600;' }, [ m.licenseCount + ( 1 === m.licenseCount ? ' license included' : ' licenses included' ) ] ) );
			var list = el( 'ul', { class: 'ssw-feature-list' } );
			( m.features || [] ).forEach( function ( f ) { list.appendChild( el( 'li', {}, [ f ] ) ); } );
			card.appendChild( list );
			if ( selected && locked ) card.appendChild( el( 'div', { class: 'ssw-card-locked-note' }, [ 'Included in your Enterprise Plan' ] ) );
			card.addEventListener( 'click', function () {
				if ( this.state.enterpriseSelected ) return;
				this.state.measureTitle = m.title;
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );

		if ( ( this.config.catalog.measureAddons || [] ).length ) {
			wrap.appendChild( el( 'h4', { class: 'ssw-subsection-heading' }, [ 'Report add-ons' ] ) );
			var addonGrid = el( 'div', { class: 'ssw-grid-fixed3' } );
			this.config.catalog.measureAddons.forEach( function ( a ) {
				var selected = this.state.selectedMeasureAddonSlugs.indexOf( a.slug ) !== -1;
				var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
				var aHeader = el( 'div', { class: 'ssw-solution-header', style: a.icon ? '' : 'justify-content:flex-end;' } );
				if ( a.icon ) aHeader.appendChild( el( 'img', { class: 'ssw-card-icon', src: a.icon, alt: '' } ) );
				aHeader.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( a.price ) ] ) );
				card.appendChild( aHeader );
				card.appendChild( el( 'h4', {}, [ a.title ] ) );
				card.appendChild( el( 'p', {}, [ a.licensesIncluded + ' licenses included' ] ) );
				card.addEventListener( 'click', function () {
					var idx = this.state.selectedMeasureAddonSlugs.indexOf( a.slug );
					if ( idx === -1 ) this.state.selectedMeasureAddonSlugs.push( a.slug );
					else this.state.selectedMeasureAddonSlugs.splice( idx, 1 );
					this.persist();
					this.render();
				}.bind( this ) );
				addonGrid.appendChild( card );
			}.bind( this ) );
			wrap.appendChild( addonGrid );
		}
		return wrap;
	};

	SSWWizard.prototype.renderChoicesBespokeSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.bespokeSection || 'Bespoke Development' ] ) );

		( this.config.catalog.bespoke || [] ).forEach( function ( b ) {
			var row = el( 'div', { class: 'ssw-bespoke-row' } );
			var label = el( 'span', { style: 'display:flex;align-items:center;gap:10px;' } );
			if ( b.icon ) label.appendChild( el( 'img', { class: 'ssw-bespoke-icon', src: b.icon, alt: '' } ) );
			label.appendChild( el( 'span', {}, [ b.title ] ) );
			row.appendChild( label );
			row.appendChild( el( 'span', { class: 'ssw-bespoke-price' }, [ b.priceLabel ] ) );
			wrap.appendChild( row );
		} );

		var interestWrap = el( 'label', { class: 'ssw-bespoke-interest' } );
		var cb = el( 'input', { type: 'checkbox' } );
		cb.checked = !! this.state.bespokeInterested;
		cb.addEventListener( 'change', function () {
			this.state.bespokeInterested = cb.checked;
			this.persist();
			this.render();
		}.bind( this ) );
		interestWrap.appendChild( cb );
		interestWrap.appendChild( el( 'span', {}, [ "Tell us what you need — we'll follow up" ] ) );
		wrap.appendChild( interestWrap );

		if ( this.state.bespokeInterested ) {
			var ta = el( 'textarea', { class: 'ssw-bespoke-notes', placeholder: 'A sentence or two about what you have in mind…', rows: '3' } );
			ta.value = this.state.bespokeNotes || '';
			ta.addEventListener( 'input', function () { this.state.bespokeNotes = ta.value; this.persist(); }.bind( this ) );
			wrap.appendChild( ta );
		}
		return wrap;
	};

	SSWWizard.prototype.renderChoicesEnterpriseSection = function () {
		var ent = this.config.catalog.enterprise;
		if ( ! ent || ! ent.title ) return el( 'div', {} );

		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.enterpriseSection || 'Want it all?' ] ) );

		var banner = el( 'div', { class: 'ssw-enterprise-banner' + ( this.state.enterpriseSelected ? ' selected' : '' ) } );
		var textWrap = el( 'div', { style: 'display:flex;align-items:center;gap:16px;' } );
		if ( ent.icon ) textWrap.appendChild( el( 'img', { class: 'ssw-enterprise-icon', src: ent.icon, alt: '' } ) );
		var text = el( 'div', {} );
		text.appendChild( el( 'h4', {}, [ ent.title ] ) );
		text.appendChild( el( 'p', {}, [ ent.priceLabel + ' — includes ' + ent.tierTitle + ' Website Package, ' + ent.marketingTitle + ' Marketing, and ' + ent.measureTitle + ' Measure Analytics.' ] ) );
		textWrap.appendChild( text );
		banner.appendChild( textWrap );
		var btn = el( 'button', { type: 'button', class: 'ssw-btn' + ( this.state.enterpriseSelected ? ' ghost' : '' ) }, [ this.state.enterpriseSelected ? 'Remove bundle' : 'Select Enterprise' ] );
		btn.addEventListener( 'click', function () { this.toggleEnterprise(); }.bind( this ) );
		banner.appendChild( btn );
		wrap.appendChild( banner );
		return wrap;
	};

	SSWWizard.prototype.toggleEnterprise = function () {
		var ent = this.config.catalog.enterprise;
		if ( this.state.enterpriseSelected ) {
			this.state.enterpriseSelected = false;
			this.state.tierTitle = '';
			this.state.marketingTitle = '';
			this.state.measureTitle = '';
		} else {
			this.state.enterpriseSelected = true;
			this.state.tierTitle = ent.tierTitle;
			this.state.marketingTitle = ent.marketingTitle;
			this.state.measureTitle = ent.measureTitle;
		}
		this.persist();
		this.render();
	};

	/* ------------------------------------------------------------ solutions step */

	SSWWizard.prototype.renderSolutionsPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		wrap.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.solutionsHeading || 'Pick your solutions' ] ) );

		if ( this.state.tierTitle ) {
			wrap.appendChild( this.renderPointsBar() );
		}

		var grid = el( 'div', { class: 'ssw-grid' } );
		this.config.solutions.forEach( function ( sol ) {
			var selected = this.state.selectedSlugs.indexOf( sol.slug ) !== -1;
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var header = el( 'div', { class: 'ssw-solution-header' } );
			if ( sol.icon ) header.appendChild( el( 'img', { class: 'ssw-card-icon', src: sol.icon, alt: '' } ) );
			header.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ sol.points + ' pts' ] ) );
			card.appendChild( header );
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
			var overMsg = this.isSinglePage()
				// USD is shown on every module card in this mode, so the
				// shortfall is just informational, not a hidden-price notice.
				? "You're " + ( used - included ) + ' points over your ' + ( tier ? tier.title : 'package' ) + " allowance — that's fine, any modules beyond your points are simply billed at their listed price."
				: "You're " + ( used - included ) + ' points over your ' + ( tier ? tier.title : 'package' ) + " allowance — that's fine, we'll include it in your proposal.";
			wrap.appendChild( el( 'div', { class: 'ssw-shortfall-banner' }, [ overMsg ] ) );
		}
		return wrap;
	};

	/* ------------------------------------------------------------ checkout step */

	SSWWizard.prototype.renderCheckoutPanel = function () {
		var wrap = el( 'div', { class: 'ssw-panel active' } );
		var layout = el( 'div', { class: 'ssw-checkout-layout' } );
		var formCol = el( 'div', { class: 'ssw-checkout-form' } );
		formCol.appendChild( el( 'h3', { class: 'ssw-heading' }, [ this.config.checkoutHeading || 'Your Details' ] ) );

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

		var phoneCode = null;
		( function () {
			var required = !! fields.phone;
			var fieldWrap = el( 'div', { class: 'ssw-field' } );
			fieldWrap.appendChild( el( 'label', {}, [ 'Phone' + ( required ? ' *' : ' (optional)' ) ] ) );
			var row = el( 'div', { class: 'ssw-phone-row' } );
			phoneCode = el( 'select', {} );
			PHONE_CODES.forEach( function ( pair ) {
				phoneCode.appendChild( el( 'option', { value: pair[ 0 ] }, [ pair[ 1 ] ] ) );
			} );
			var input = el( 'input', { type: 'tel', name: 'phone' } );
			if ( required ) input.setAttribute( 'required', 'required' );
			row.appendChild( phoneCode );
			row.appendChild( input );
			fieldWrap.appendChild( row );
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

		// Shared by blur (real-time feedback) and submit (final gate) so the
		// two never drift out of sync.
		var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
		function validateField( key ) {
			var input = inputs[ key ].el;
			var requiredKey = inputs[ key ].requiredKey;
			var fieldWrap = input.closest( '.ssw-field' );
			var value = input.value.trim();
			var ok = true;
			if ( requiredKey && fields[ requiredKey ] && ! value ) ok = false;
			if ( key === 'email' && value && ! EMAIL_RE.test( value ) ) ok = false;
			fieldWrap.classList.toggle( 'error', ! ok );
			return ok;
		}
		Object.keys( inputs ).forEach( function ( key ) {
			inputs[ key ].el.addEventListener( 'blur', function () { validateField( key ); } );
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
				if ( ! validateField( key ) ) valid = false;
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
				// single-page builder ("Build Your Business") fields — empty/false in classic mode
				domain_wanted: !! this.state.domainWanted,
				domain_name: this.state.domainName || '',
				marketing_title: this.state.marketingTitle || '',
				licenses: this.selectedLicenses().map( function ( l ) { return { title: l.title, price: l.price }; } ),
				measure_title: this.state.measureTitle || '',
				measure_addons: this.selectedMeasureAddons().map( function ( a ) { return { title: a.title, price: a.price }; } ),
				bespoke_interested: !! this.state.bespokeInterested,
				bespoke_notes: this.state.bespokeNotes || '',
				enterprise_selected: !! this.state.enterpriseSelected,
				phone_country_code: phoneCode ? phoneCode.value : '',
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
			list.appendChild( row( [
				el( 'span', { class: 'ssw-order-icon-svg', html: DOMAIN_SVG } ),
				el( 'span', {}, [ 'Domain: ' + this.state.domain ] ),
			], '—' ) );
			rowCount++;
		}
		if ( this.isSinglePage() && this.state.domainWanted ) {
			list.appendChild( row( [
				el( 'span', { class: 'ssw-order-icon-svg', html: DOMAIN_SVG } ),
				el( 'span', {}, [ 'Domain wanted: ' + ( this.state.domainName || '(name not given yet)' ) ] ),
			], '—' ) );
			rowCount++;
		}

		this.selectedSolutions().forEach( function ( s ) {
			var mainNodes = [];
			if ( s.icon ) mainNodes.push( el( 'img', { class: 'ssw-order-icon', src: s.icon, alt: '' } ) );
			mainNodes.push( el( 'span', {}, [ s.title ] ) );
			list.appendChild( row( mainNodes, String( s.points ) ) );
			rowCount++;
		}.bind( this ) );

		if ( this.isSinglePage() ) {
			var marketing = this.selectedMarketing();
			if ( marketing ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Marketing: ' + marketing.title ] ) ], money( marketing.price ) + '/mo' ) );
				rowCount++;
			}
			this.selectedLicenses().forEach( function ( l ) {
				list.appendChild( row( [ el( 'span', {}, [ l.title ] ) ], money( l.price ) + '/mo' ) );
				rowCount++;
			} );
			var measure = this.selectedMeasureTier();
			if ( measure ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Measure Analytics: ' + measure.title ] ) ], money( measure.price ) + '/mo' ) );
				rowCount++;
			}
			this.selectedMeasureAddons().forEach( function ( a ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Measure report: ' + a.title ] ) ], money( a.price ) ) );
				rowCount++;
			} );
			if ( this.state.bespokeInterested ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Bespoke Development — interested' ] ) ], '—' ) );
				rowCount++;
			}
			if ( this.state.enterpriseSelected ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Enterprise bundle' ] ) ], '—' ) );
				rowCount++;
			}
		}

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

			var diff = used - included;
			var totalLabel = diff > 0 ? diff + ' pts over budget' : ( -diff ) + ' pts remaining';
			var total = el( 'div', { class: 'ssw-order-total' + ( diff > 0 ? ' over' : '' ) }, [
				el( 'span', {}, [ 'Points' ] ),
				el( 'span', {}, [ totalLabel ] ),
			] );
			box.appendChild( total );
		} else if ( ! this.isSinglePage() && used ) {
			box.appendChild( el( 'div', { class: 'ssw-order-total' }, [
				el( 'span', {}, [ 'Total' ] ),
				el( 'span', {}, [ used + ' pts' ] ),
			] ) );
		}

		if ( this.isSinglePage() ) {
			var usd = this.usdTotal();
			if ( usd > 0 ) {
				box.appendChild( el( 'div', { class: 'ssw-order-total' }, [
					el( 'span', {}, [ 'Monthly total (excl. Website Package)' ] ),
					el( 'span', {}, [ money( usd ) + '/mo' ] ),
				] ) );
			}
		}

		return box;
	};

	SSWWizard.prototype.renderThankYou = function () {
		this.root.innerHTML = '';
		this.root.appendChild(
			el( 'div', { class: 'ssw-thankyou' }, [
				el( 'div', { class: 'ssw-thankyou-icon', html: CHECK_CIRCLE_SVG } ),
				el( 'h3', {}, [ this.config.thankyouHeading || "You're all set!" ] ),
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
