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
	// A near-complete ITU calling-code list (not just the handful of markets
	// Strivre was seeing leads from), sorted alphabetically by country name
	// (not by dial code) so visitors can actually find theirs by scanning.
	var PHONE_CODES = [
		[ '+93', "Afghanistan +93" ], [ '+355', "Albania +355" ],
		[ '+213', "Algeria +213" ], [ '+376', "Andorra +376" ],
		[ '+244', "Angola +244" ], [ '+54', "Argentina +54" ],
		[ '+374', "Armenia +374" ], [ '+61', "Australia +61" ],
		[ '+43', "Austria +43" ], [ '+994', "Azerbaijan +994" ],
		[ '+973', "Bahrain +973" ], [ '+880', "Bangladesh +880" ],
		[ '+375', "Belarus +375" ], [ '+32', "Belgium +32" ],
		[ '+501', "Belize +501" ], [ '+229', "Benin +229" ],
		[ '+975', "Bhutan +975" ], [ '+591', "Bolivia +591" ],
		[ '+387', "Bosnia and Herzegovina +387" ], [ '+267', "Botswana +267" ],
		[ '+55', "Brazil +55" ], [ '+673', "Brunei +673" ],
		[ '+359', "Bulgaria +359" ], [ '+226', "Burkina Faso +226" ],
		[ '+257', "Burundi +257" ], [ '+238', "Cabo Verde +238" ],
		[ '+855', "Cambodia +855" ], [ '+237', "Cameroon +237" ],
		[ '+236', "Central African Republic +236" ], [ '+235', "Chad +235" ],
		[ '+56', "Chile +56" ], [ '+86', "China +86" ],
		[ '+57', "Colombia +57" ], [ '+242', "Congo (Brazzaville) +242" ],
		[ '+682', "Cook Islands +682" ], [ '+506', "Costa Rica +506" ],
		[ '+225', "Cote d'Ivoire +225" ], [ '+385', "Croatia +385" ],
		[ '+53', "Cuba +53" ], [ '+357', "Cyprus +357" ],
		[ '+420', "Czechia +420" ], [ '+45', "Denmark +45" ],
		[ '+253', "Djibouti +253" ], [ '+243', "DR Congo +243" ],
		[ '+593', "Ecuador +593" ], [ '+20', "Egypt +20" ],
		[ '+503', "El Salvador +503" ], [ '+240', "Equatorial Guinea +240" ],
		[ '+372', "Estonia +372" ], [ '+268', "Eswatini +268" ],
		[ '+251', "Ethiopia +251" ], [ '+500', "Falkland Islands +500" ],
		[ '+679', "Fiji +679" ], [ '+358', "Finland +358" ],
		[ '+33', "France +33" ], [ '+689', "French Polynesia +689" ],
		[ '+241', "Gabon +241" ], [ '+220', "Gambia +220" ],
		[ '+995', "Georgia +995" ], [ '+49', "Germany +49" ],
		[ '+233', "Ghana +233" ], [ '+30', "Greece +30" ],
		[ '+502', "Guatemala +502" ], [ '+224', "Guinea +224" ],
		[ '+245', "Guinea-Bissau +245" ], [ '+592', "Guyana +592" ],
		[ '+509', "Haiti +509" ], [ '+504', "Honduras +504" ],
		[ '+852', "Hong Kong +852" ], [ '+36', "Hungary +36" ],
		[ '+354', "Iceland +354" ], [ '+91', "India +91" ],
		[ '+62', "Indonesia +62" ], [ '+98', "Iran +98" ],
		[ '+964', "Iraq +964" ], [ '+353', "Ireland +353" ],
		[ '+972', "Israel +972" ], [ '+39', "Italy +39" ],
		[ '+81', "Japan +81" ], [ '+962', "Jordan +962" ],
		[ '+254', "Kenya +254" ], [ '+686', "Kiribati +686" ],
		[ '+383', "Kosovo +383" ], [ '+965', "Kuwait +965" ],
		[ '+996', "Kyrgyzstan +996" ], [ '+856', "Laos +856" ],
		[ '+371', "Latvia +371" ], [ '+961', "Lebanon +961" ],
		[ '+266', "Lesotho +266" ], [ '+231', "Liberia +231" ],
		[ '+218', "Libya +218" ], [ '+423', "Liechtenstein +423" ],
		[ '+370', "Lithuania +370" ], [ '+352', "Luxembourg +352" ],
		[ '+853', "Macau +853" ], [ '+261', "Madagascar +261" ],
		[ '+265', "Malawi +265" ], [ '+60', "Malaysia +60" ],
		[ '+960', "Maldives +960" ], [ '+223', "Mali +223" ],
		[ '+356', "Malta +356" ], [ '+692', "Marshall Islands +692" ],
		[ '+222', "Mauritania +222" ], [ '+230', "Mauritius +230" ],
		[ '+52', "Mexico +52" ], [ '+691', "Micronesia +691" ],
		[ '+373', "Moldova +373" ], [ '+377', "Monaco +377" ],
		[ '+976', "Mongolia +976" ], [ '+382', "Montenegro +382" ],
		[ '+212', "Morocco +212" ], [ '+258', "Mozambique +258" ],
		[ '+95', "Myanmar +95" ], [ '+264', "Namibia +264" ],
		[ '+674', "Nauru +674" ], [ '+977', "Nepal +977" ],
		[ '+31', "Netherlands +31" ], [ '+64', "New Zealand +64" ],
		[ '+505', "Nicaragua +505" ], [ '+227', "Niger +227" ],
		[ '+234', "Nigeria +234" ], [ '+850', "North Korea +850" ],
		[ '+389', "North Macedonia +389" ], [ '+47', "Norway +47" ],
		[ '+968', "Oman +968" ], [ '+92', "Pakistan +92" ],
		[ '+680', "Palau +680" ], [ '+970', "Palestine +970" ],
		[ '+507', "Panama +507" ], [ '+675', "Papua New Guinea +675" ],
		[ '+595', "Paraguay +595" ], [ '+51', "Peru +51" ],
		[ '+63', "Philippines +63" ], [ '+48', "Poland +48" ],
		[ '+351', "Portugal +351" ], [ '+974', "Qatar +974" ],
		[ '+40', "Romania +40" ], [ '+7', "Russia/Kazakhstan +7" ],
		[ '+250', "Rwanda +250" ], [ '+685', "Samoa +685" ],
		[ '+378', "San Marino +378" ], [ '+239', "Sao Tome and Principe +239" ],
		[ '+966', "Saudi Arabia +966" ], [ '+221', "Senegal +221" ],
		[ '+381', "Serbia +381" ], [ '+248', "Seychelles +248" ],
		[ '+232', "Sierra Leone +232" ], [ '+65', "Singapore +65" ],
		[ '+421', "Slovakia +421" ], [ '+386', "Slovenia +386" ],
		[ '+677', "Solomon Islands +677" ], [ '+252', "Somalia +252" ],
		[ '+27', "South Africa +27" ], [ '+82', "South Korea +82" ],
		[ '+211', "South Sudan +211" ], [ '+34', "Spain +34" ],
		[ '+94', "Sri Lanka +94" ], [ '+249', "Sudan +249" ],
		[ '+597', "Suriname +597" ], [ '+46', "Sweden +46" ],
		[ '+41', "Switzerland +41" ], [ '+963', "Syria +963" ],
		[ '+886', "Taiwan +886" ], [ '+992', "Tajikistan +992" ],
		[ '+255', "Tanzania +255" ], [ '+66', "Thailand +66" ],
		[ '+670', "Timor-Leste +670" ], [ '+228', "Togo +228" ],
		[ '+676', "Tonga +676" ], [ '+216', "Tunisia +216" ],
		[ '+90', "Turkey +90" ], [ '+993', "Turkmenistan +993" ],
		[ '+256', "Uganda +256" ], [ '+380', "Ukraine +380" ],
		[ '+971', "United Arab Emirates +971" ], [ '+44', "United Kingdom +44" ],
		[ '+598', "Uruguay +598" ], [ '+1', "US/Canada +1" ],
		[ '+998', "Uzbekistan +998" ], [ '+678', "Vanuatu +678" ],
		[ '+58', "Venezuela +58" ], [ '+84', "Vietnam +84" ],
		[ '+967', "Yemen +967" ], [ '+260', "Zambia +260" ],
		[ '+263', "Zimbabwe +263" ],
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
			moduleQuantities: {},
			moduleWebsiteQuantities: {},
			selectedLicenseSlugs: [],
			licenseQuantities: {},
			measureTitle: '',
			selectedMeasureAddonSlugs: [],
			measureAddonQuantities: {},
			measureTierLicenseQuantities: {},
			domainWanted: null,
			domainName: '',
			selectedBespokeSlugs: [],
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
		this.selectedSolutions().forEach( function ( s ) { total += s.points * this.moduleTotalMultiplier( s ); }.bind( this ) );
		return total;
	};

	SSWWizard.prototype.selectedSolutions = function () {
		var slugs = this.state.selectedSlugs;
		return this.modulesList().filter( function ( s ) { return slugs.indexOf( s.slug ) !== -1; } );
	};

	// Every billable unit note in the pricing PDF gets its own dropdown kind:
	// "Per user" / "Per license" -> a headcount dropdown (unchanged from
	// before), "Per website [page] / month" -> a term-length dropdown capped
	// at 12 months, "Per report generated - free for first report" -> a
	// report-count dropdown where the first unit is free. Driven off the
	// catalog's own unit-note text so new rows opt in automatically.
	SSWWizard.prototype.unitKindFor = function ( item ) {
		var note = ( item && item.unitNote || '' ).toLowerCase();
		if ( /^per user/.test( note ) ) return 'user';
		if ( /^per license/.test( note ) ) return 'license';
		if ( /^per report generated/.test( note ) ) return 'report';
		if ( /^per website( page)?\s*\/\s*month/.test( note ) ) return 'month';
		return '';
	};

	SSWWizard.prototype.isPerUnit = function ( item ) {
		return !! this.unitKindFor( item );
	};

	SSWWizard.prototype.moduleQtyFor = function ( mod ) {
		// "Per website/month" modules dropped their months dropdown per
		// client request — billed per website only now, so this always
		// stays 1 for that kind (guards against stale persisted state too).
		if ( ! this.isPerUnit( mod ) || 'month' === this.unitKindFor( mod ) ) return 1;
		return this.state.moduleQuantities[ mod.slug ] || 1;
	};

	// The billable count differs from the selected dropdown value for
	// "report" items only — the first report is free per the PDF, so 1
	// selected report bills 0, 2 selected bills 1, etc.
	SSWWizard.prototype.moduleBillableQty = function ( mod ) {
		var qty = this.moduleQtyFor( mod );
		return 'report' === this.unitKindFor( mod ) ? Math.max( 0, qty - 1 ) : qty;
	};

	// Every item gets exactly one quantity dropdown, matching its own
	// "per X" unit note — "per website/month" modules dropped their months
	// dropdown (see moduleQtyFor above) in favor of this single "how many
	// websites?" one; "per user" modules stay a single "how many users?"
	// dropdown (see the primary one below) with no second dimension.
	SSWWizard.prototype.moduleHasWebsiteQty = function ( mod ) {
		return 'month' === this.unitKindFor( mod );
	};

	SSWWizard.prototype.moduleWebsiteQtyFor = function ( mod ) {
		if ( ! this.moduleHasWebsiteQty( mod ) ) return 1;
		return this.state.moduleWebsiteQuantities[ mod.slug ] || 1;
	};

	// The single multiplier to use everywhere a module's price/points are
	// totaled — billable months/users/reports times the website count.
	SSWWizard.prototype.moduleTotalMultiplier = function ( mod ) {
		return this.moduleBillableQty( mod ) * this.moduleWebsiteQtyFor( mod );
	};

	SSWWizard.prototype.licenseQtyFor = function ( lic ) {
		if ( ! this.isPerUnit( lic ) ) return 1;
		return this.state.licenseQuantities[ lic.slug ] || 1;
	};

	var QTY_KIND_CONFIG = { user: [ 'users', 1, 20 ], license: [ 'licenses', 1, 20 ], month: [ 'months', 1, 12 ], report: [ 'reports', 1, 12 ] };
	SSWWizard.prototype.qtyUnitLabel = function ( kind ) {
		return ( QTY_KIND_CONFIG[ kind ] || [ 'units' ] )[ 0 ];
	};

	// Price badge suffix, matching the "/mo/brand" style already used on
	// Marketing tiers — "per website/month" and "per user/month" spelled
	// out on the badge itself instead of a bare "/mo".
	SSWWizard.prototype.priceSuffixFor = function ( kind ) {
		if ( 'month' === kind ) return '/mo/website';
		if ( 'user' === kind ) return '/mo/user';
		if ( 'license' === kind ) return '/mo/license';
		return '';
	};

	// Volume discount straight from the pricing PDF's "Add-On Modules" table
	// (5 licenses = full price, then discount brackets up to 100).
	SSWWizard.prototype.measureAddonDiscountRate = function ( qty ) {
		if ( qty >= 51 ) return 0.25;
		if ( qty >= 26 ) return 0.20;
		if ( qty >= 11 ) return 0.15;
		if ( qty >= 6 ) return 0.10;
		return 0;
	};

	SSWWizard.prototype.measureAddonQtyFor = function ( addon ) {
		return this.state.measureAddonQuantities[ addon.slug ] || addon.licensesIncluded || 5;
	};

	SSWWizard.prototype.measureAddonPriceFor = function ( addon ) {
		var qty = this.measureAddonQtyFor( addon );
		var perLicense = addon.price / ( addon.licensesIncluded || 5 );
		var rate = this.measureAddonDiscountRate( qty );
		return Math.round( perLicense * qty * ( 1 - rate ) * 100 ) / 100;
	};

	// Measure Analytics tiers each have their own "Add-On License" rate for
	// licenses beyond the tier's included count (Forever Free has no
	// add-on option at all per the PDF — addonPrice is 0 for that row).
	SSWWizard.prototype.measureTierHasAddonLicenses = function ( tier ) {
		return !! ( tier && tier.addonPrice > 0 );
	};

	SSWWizard.prototype.measureTierLicenseQtyFor = function ( tier ) {
		var included = tier.licenseCount || 1;
		if ( ! this.measureTierHasAddonLicenses( tier ) ) return included;
		return this.state.measureTierLicenseQuantities[ tier.slug ] || included;
	};

	SSWWizard.prototype.measureTierPriceFor = function ( tier ) {
		var qty = this.measureTierLicenseQtyFor( tier );
		var extra = Math.max( 0, qty - ( tier.licenseCount || 1 ) );
		return tier.price + extra * tier.addonPrice;
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

	// Bespoke Development is quote-based, not fixed-price — selecting items
	// here is deliberately never added into usdTotal() below.
	SSWWizard.prototype.selectedBespoke = function () {
		if ( ! this.config.catalog ) return [];
		var slugs = this.state.selectedBespokeSlugs;
		return this.config.catalog.bespoke.filter( function ( b ) { return slugs.indexOf( b.slug ) !== -1; } );
	};

	/** Real-USD running total across every priced section, including the
	 *  Website Package and Website Modules — the package is the primary
	 *  thing being sold here, so its price (and any add-on modules) belong
	 *  in the headline total, not just tracked in points. `s.price || 0`
	 *  guards classic mode, whose solutions never carry a price field. */
	SSWWizard.prototype.usdTotal = function () {
		var total = 0;
		var tier = this.selectedTier();
		if ( tier ) total += tier.price;
		this.selectedSolutions().forEach( function ( s ) { total += ( s.price || 0 ) * this.moduleTotalMultiplier( s ); }.bind( this ) );
		var marketing = this.selectedMarketing();
		if ( marketing ) total += marketing.price;
		this.selectedLicenses().forEach( function ( l ) { total += l.price * this.licenseQtyFor( l ); }.bind( this ) );
		var measure = this.selectedMeasureTier();
		if ( measure ) total += this.measureTierPriceFor( measure );
		this.selectedMeasureAddons().forEach( function ( a ) { total += this.measureAddonPriceFor( a ); }.bind( this ) );
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
		if ( this.config.enableChoicesDomain !== false ) wrap.appendChild( this.renderChoicesDomainSection() );
		if ( this.config.enableChoicesModules !== false ) wrap.appendChild( this.renderChoicesModulesSection() );
		if ( this.config.enableChoicesMarketing !== false ) wrap.appendChild( this.renderChoicesMarketingSection() );
		if ( this.config.enableChoicesLicenses !== false ) wrap.appendChild( this.renderChoicesLicensesSection() );
		if ( this.config.enableChoicesMeasure ) wrap.appendChild( this.renderChoicesMeasureSection() );
		if ( this.config.enableChoicesMeasureAddons !== false ) wrap.appendChild( this.renderChoicesMeasureAddonsSection() );
		if ( this.config.enableChoicesBespoke !== false ) wrap.appendChild( this.renderChoicesBespokeSection() );
		if ( this.config.enableChoicesEnterprise !== false ) wrap.appendChild( this.renderChoicesEnterpriseSection() );

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
			var kind = this.unitKindFor( mod );
			var qty = selected ? this.moduleQtyFor( mod ) : 1;
			var billableQty = selected ? this.moduleBillableQty( mod ) : 1;
			var websiteQty = selected ? this.moduleWebsiteQtyFor( mod ) : 1;
			var totalMultiplier = billableQty * websiteQty;
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var header = el( 'div', { class: 'ssw-solution-header', style: mod.icon ? '' : 'justify-content:flex-end;' } );
			if ( mod.icon ) header.appendChild( el( 'img', { class: 'ssw-card-icon', src: mod.icon, alt: '' } ) );
			var badges = el( 'div', { style: 'display:flex;flex-direction:column;align-items:flex-end;gap:4px;' } );
			badges.appendChild( el( 'div', { class: 'ssw-points-badge' }, [ ( mod.points * totalMultiplier ) + ' pts' ] ) );
			badges.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( mod.price * totalMultiplier ) + this.priceSuffixFor( kind ) ] ) );
			header.appendChild( badges );
			card.appendChild( header );
			card.appendChild( el( 'h4', {}, [ mod.title ] ) );
			if ( mod.unitNote ) card.appendChild( el( 'p', {}, [ mod.unitNote ] ) );
			if ( selected && kind ) {
				if ( this.moduleHasWebsiteQty( mod ) ) {
					card.appendChild( this.renderQuantityField( 'moduleWebsiteQuantities', mod.slug, websiteQty, 'websites', 1, 20 ) );
				}
				// "Per website/month" modules are billed per website only
				// now (no separate months dropdown) per client request.
				if ( 'month' !== kind ) {
					var cfg = QTY_KIND_CONFIG[ kind ];
					card.appendChild( this.renderQuantityField( 'moduleQuantities', mod.slug, qty, cfg[ 0 ], cfg[ 1 ], cfg[ 2 ] ) );
				}
				if ( 'report' === kind ) {
					card.appendChild( el( 'p', { class: 'ssw-qty-note' }, [
						'First report is free — this is ' + billableQty + ' paid report' + ( 1 === billableQty ? '' : 's' ) + '.',
					] ) );
				}
			}
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedSlugs.indexOf( mod.slug );
				if ( idx === -1 ) this.state.selectedSlugs.push( mod.slug );
				else {
					this.state.selectedSlugs.splice( idx, 1 );
					delete this.state.moduleQuantities[ mod.slug ];
					delete this.state.moduleWebsiteQuantities[ mod.slug ];
				}
				this.persist();
				this.render();
			}.bind( this ) );
			grid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( grid );
		return wrap;
	};

	/** Quantity dropdown for per-user/per-license catalog items — stops
	 *  propagation so choosing a value doesn't also toggle the card's
	 *  selection state (the click handler lives on the card itself). */
	SSWWizard.prototype.renderQuantityField = function ( stateKey, slug, qty, unitLabel, min, max ) {
		min = min || 1;
		max = max || 20;
		var wrap = el( 'div', { class: 'ssw-qty-field' } );
		wrap.appendChild( el( 'label', {}, [ 'How many ' + unitLabel + '?' ] ) );
		var select = el( 'select' );
		for ( var i = min; i <= max; i++ ) {
			var opt = el( 'option', { value: String( i ) }, [ String( i ) ] );
			if ( i === qty ) opt.setAttribute( 'selected', 'selected' );
			select.appendChild( opt );
		}
		select.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );
		select.addEventListener( 'change', function () {
			this.state[ stateKey ][ slug ] = parseInt( select.value, 10 ) || min;
			this.persist();
			this.render();
		}.bind( this ) );
		wrap.appendChild( select );
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
			var perUnit = this.isPerUnit( lic );
			var qty = selected ? this.licenseQtyFor( lic ) : 1;
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var licHeader = el( 'div', { class: 'ssw-solution-header', style: lic.icon ? '' : 'justify-content:flex-end;' } );
			if ( lic.icon ) licHeader.appendChild( el( 'img', { class: 'ssw-card-icon', src: lic.icon, alt: '' } ) );
			licHeader.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( lic.price * qty ) + '/mo/user' ] ) );
			card.appendChild( licHeader );
			card.appendChild( el( 'h4', {}, [ lic.title ] ) );
			if ( lic.unitNote ) card.appendChild( el( 'p', {}, [ lic.unitNote ] ) );
			if ( selected && perUnit ) {
				card.appendChild( this.renderQuantityField( 'licenseQuantities', lic.slug, qty, 'users' ) );
			}
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedLicenseSlugs.indexOf( lic.slug );
				if ( idx === -1 ) this.state.selectedLicenseSlugs.push( lic.slug );
				else {
					this.state.selectedLicenseSlugs.splice( idx, 1 );
					delete this.state.licenseQuantities[ lic.slug ];
				}
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
			mHeader.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( this.measureTierPriceFor( m ) ) + '/mo' ] ) );
			card.appendChild( mHeader );
			card.appendChild( el( 'h4', {}, [ m.title ] ) );
			card.appendChild( el( 'p', { style: 'font-weight:600;' }, [ m.licenseCount + ( 1 === m.licenseCount ? ' license included' : ' licenses included' ) ] ) );
			var list = el( 'ul', { class: 'ssw-feature-list' } );
			( m.features || [] ).forEach( function ( f ) { list.appendChild( el( 'li', {}, [ f ] ) ); } );
			card.appendChild( list );
			if ( selected && this.measureTierHasAddonLicenses( m ) ) {
				card.appendChild( this.renderQuantityField( 'measureTierLicenseQuantities', m.slug, this.measureTierLicenseQtyFor( m ), 'licenses', m.licenseCount, 100 ) );
				card.appendChild( el( 'p', { class: 'ssw-qty-note' }, [
					m.licenseCount + ' included, extra licenses billed at ' + money( m.addonPrice ) + '/license/month.',
				] ) );
			}
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
		return wrap;
	};

	// A standalone section, deliberately not gated by enableChoicesMeasure —
	// the client wants Report Add-ons shown regardless of whether the
	// Measure Analytics tiers section above is enabled.
	SSWWizard.prototype.renderChoicesMeasureAddonsSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		if ( ! ( this.config.catalog.measureAddons || [] ).length ) return wrap;

		wrap.appendChild( el( 'h4', {}, [ this.config.headings.measureAddonsSection || 'Report Add-ons' ] ) );
		var addonGrid = el( 'div', { class: 'ssw-grid-fixed3' } );
		this.config.catalog.measureAddons.forEach( function ( a ) {
			var selected = this.state.selectedMeasureAddonSlugs.indexOf( a.slug ) !== -1;
			var qty = this.measureAddonQtyFor( a );
			var card = el( 'div', { class: 'ssw-card ssw-solution-card' + ( selected ? ' selected' : '' ) } );
			var aHeader = el( 'div', { class: 'ssw-solution-header', style: a.icon ? '' : 'justify-content:flex-end;' } );
			if ( a.icon ) aHeader.appendChild( el( 'img', { class: 'ssw-card-icon', src: a.icon, alt: '' } ) );
			var aBadges = el( 'div', { style: 'display:flex;flex-direction:column;align-items:flex-end;gap:4px;' } );
			aBadges.appendChild( el( 'div', { class: 'ssw-price-badge' }, [ money( this.measureAddonPriceFor( a ) ) ] ) );
			var rate = this.measureAddonDiscountRate( qty );
			if ( selected && rate > 0 ) {
				aBadges.appendChild( el( 'div', { class: 'ssw-discount-badge' }, [ ( rate * 100 ) + '% off' ] ) );
			}
			aHeader.appendChild( aBadges );
			card.appendChild( aHeader );
			card.appendChild( el( 'h4', {}, [ a.title ] ) );
			if ( selected ) {
				card.appendChild( this.renderQuantityField( 'measureAddonQuantities', a.slug, qty, 'licenses', 5, 100 ) );
			} else {
				card.appendChild( el( 'p', {}, [ ( a.licensesIncluded || 5 ) + ' licenses included' ] ) );
			}
			card.addEventListener( 'click', function () {
				var idx = this.state.selectedMeasureAddonSlugs.indexOf( a.slug );
				if ( idx === -1 ) this.state.selectedMeasureAddonSlugs.push( a.slug );
				else { this.state.selectedMeasureAddonSlugs.splice( idx, 1 ); delete this.state.measureAddonQuantities[ a.slug ]; }
				this.persist();
				this.render();
			}.bind( this ) );
			addonGrid.appendChild( card );
		}.bind( this ) );
		wrap.appendChild( addonGrid );
		wrap.appendChild( el( 'p', { class: 'ssw-points-disclaimer' }, [
			'5 licenses is full price; volume discounts apply automatically at 6+ (10% off), 11+ (15% off), 26+ (20% off), and 51+ (25% off). Need more than 100? Contact us for custom pricing. Add-On Modules are subject to a one-time integration fee of up to US$1,000, depending on the integration requirements.',
		] ) );
		return wrap;
	};

	SSWWizard.prototype.renderChoicesBespokeSection = function () {
		var wrap = el( 'div', { class: 'ssw-catalog-section' } );
		wrap.appendChild( el( 'h4', {}, [ this.config.headings.bespokeSection || 'Bespoke Development' ] ) );

		( this.config.catalog.bespoke || [] ).forEach( function ( b, i, arr ) {
			var selected = this.state.selectedBespokeSlugs.indexOf( b.slug ) !== -1;
			var row = el( 'label', { class: 'ssw-bespoke-row' + ( selected ? ' selected' : '' ) + ( i === arr.length - 1 ? ' last' : '' ) } );
			var label = el( 'span', { style: 'display:flex;align-items:center;gap:10px;' } );
			var cb = el( 'input', { type: 'checkbox' } );
			cb.checked = selected;
			cb.addEventListener( 'change', function () {
				var idx = this.state.selectedBespokeSlugs.indexOf( b.slug );
				if ( idx === -1 ) this.state.selectedBespokeSlugs.push( b.slug );
				else this.state.selectedBespokeSlugs.splice( idx, 1 );
				this.persist();
				this.render();
			}.bind( this ) );
			label.appendChild( cb );
			if ( b.icon ) label.appendChild( el( 'img', { class: 'ssw-bespoke-icon', src: b.icon, alt: '' } ) );
			label.appendChild( el( 'span', {}, [ b.title ] ) );
			row.appendChild( label );
			row.appendChild( el( 'span', { class: 'ssw-bespoke-price' }, [ b.priceLabel ] ) );
			wrap.appendChild( row );
		}.bind( this ) );

		if ( this.state.selectedBespokeSlugs.length ) {
			wrap.appendChild( el( 'p', { class: 'ssw-bespoke-quote-note' }, [
				"These aren't added to your total — we'll follow up with more information and pricing on your quotation.",
			] ) );
		}

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
		text.appendChild( el( 'p', { class: 'ssw-enterprise-note' }, [
			'With a dedicated project manager. May cost extra for some features that are user-based. Subject to a one-time integration fee of up to US$1,000, depending on the integration requirements.',
		] ) );
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
		if ( this.isSinglePage() ) {
			// Straight from the pricing PDF's "Important" note — points and
			// USD are both shown side by side in this mode, so this needs to
			// be stated plainly rather than assumed.
			wrap.appendChild( el( 'p', { class: 'ssw-points-disclaimer' }, [
				'Points are for module selection only and have no cash value. Unused points cannot be converted into discounts or refunded.',
			] ) );
		}
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
			var input = el( 'input', { type: 'tel', name: 'phone', inputmode: 'tel', placeholder: '9171234567' } );
			if ( required ) input.setAttribute( 'required', 'required' );
			// Strip anything that isn't a digit/space/hyphen/parenthesis as the
			// visitor types (or pastes) — the country code already lives in the
			// select next to it, so letters have no legitimate use here.
			input.addEventListener( 'input', function () {
				var cleaned = input.value.replace( /[^\d\s\-()]/g, '' );
				if ( cleaned !== input.value ) input.value = cleaned;
			} );
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
				solutions: this.selectedSolutions().map( function ( s ) { return { title: s.title, points: s.points * this.moduleTotalMultiplier( s ), qty: this.moduleQtyFor( s ), websiteQty: this.moduleWebsiteQtyFor( s ), unit: this.unitKindFor( s ) }; }.bind( this ) ),
				page_url: this.config.pageUrl || window.location.href,
				hp: hp.value,
				started_at: this.startedAt,
				// single-page builder ("Build Your Business") fields — empty/false in classic mode
				domain_wanted: !! this.state.domainWanted,
				domain_name: this.state.domainName || '',
				marketing_title: this.state.marketingTitle || '',
				licenses: this.selectedLicenses().map( function ( l ) { return { title: l.title, price: l.price * this.licenseQtyFor( l ), qty: this.licenseQtyFor( l ) }; }.bind( this ) ),
				measure_title: this.state.measureTitle || '',
				measure_license_qty: this.selectedMeasureTier() ? this.measureTierLicenseQtyFor( this.selectedMeasureTier() ) : 0,
				measure_price: this.selectedMeasureTier() ? this.measureTierPriceFor( this.selectedMeasureTier() ) : 0,
				measure_addons: this.selectedMeasureAddons().map( function ( a ) { return { title: a.title, price: this.measureAddonPriceFor( a ), qty: this.measureAddonQtyFor( a ) }; }.bind( this ) ),
				bespoke_selected: this.selectedBespoke().map( function ( b ) { return b.title; } ),
				bespoke_interested: !! this.state.bespokeInterested,
				bespoke_notes: this.state.bespokeNotes || '',
				enterprise_selected: !! this.state.enterpriseSelected,
				monthly_total: this.usdTotal(),
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
			var qty = this.moduleQtyFor( s );
			var websiteQty = this.moduleWebsiteQtyFor( s );
			var kind = this.unitKindFor( s );
			var parts = [];
			if ( websiteQty > 1 ) parts.push( websiteQty + ' websites' );
			if ( kind && qty > 1 ) parts.push( qty + ' ' + this.qtyUnitLabel( kind ) );
			var mainNodes = [];
			if ( s.icon ) mainNodes.push( el( 'img', { class: 'ssw-order-icon', src: s.icon, alt: '' } ) );
			mainNodes.push( el( 'span', {}, [ s.title + ( parts.length ? ' (×' + parts.join( ' × ' ) + ')' : '' ) ] ) );
			list.appendChild( row( mainNodes, String( s.points * this.moduleTotalMultiplier( s ) ) ) );
			rowCount++;
		}.bind( this ) );

		if ( this.isSinglePage() ) {
			var marketing = this.selectedMarketing();
			if ( marketing ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Marketing: ' + marketing.title ] ) ], money( marketing.price ) + '/mo' ) );
				rowCount++;
			}
			this.selectedLicenses().forEach( function ( l ) {
				var lqty = this.licenseQtyFor( l );
				var lparts = [];
				if ( lqty > 1 ) lparts.push( lqty + ' users' );
				list.appendChild( row( [ el( 'span', {}, [ l.title + ( lparts.length ? ' (×' + lparts.join( ' × ' ) + ')' : '' ) ] ) ], money( lqty * l.price ) + '/mo' ) );
				rowCount++;
			}.bind( this ) );
			var measure = this.selectedMeasureTier();
			if ( measure ) {
				var mqty = this.measureTierLicenseQtyFor( measure );
				var mextra = mqty > measure.licenseCount ? ' (' + mqty + ' licenses)' : '';
				list.appendChild( row( [ el( 'span', {}, [ 'Measure Analytics: ' + measure.title + mextra ] ) ], money( this.measureTierPriceFor( measure ) ) + '/mo' ) );
				rowCount++;
			}
			this.selectedMeasureAddons().forEach( function ( a ) {
				var aqty = this.measureAddonQtyFor( a );
				list.appendChild( row( [ el( 'span', {}, [ 'Measure report: ' + a.title + ' (' + aqty + ' licenses)' ] ) ], money( this.measureAddonPriceFor( a ) ) ) );
				rowCount++;
			}.bind( this ) );
			this.selectedBespoke().forEach( function ( b ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Bespoke: ' + b.title ] ) ], 'Quote' ) );
				rowCount++;
			}.bind( this ) );
			if ( this.state.bespokeInterested ) {
				list.appendChild( row( [ el( 'span', {}, [ 'Bespoke Development — additional notes' ] ) ], '—' ) );
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
					el( 'span', {}, [ 'Monthly total' ] ),
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
