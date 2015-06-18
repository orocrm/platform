/*jslint browser:true, nomen:true, white:true, eqeq:true*/
/*global define*/
define([
    'jquery',
    'underscore',
    'oroui/js/tools',
    'chaplin'
], function ($, _, tools, Chaplin) {
    'use strict';

    var utils, location, original = {};

    utils = Chaplin.utils;
    location = window.location;
    original.viewDispose = Chaplin.View.prototype.dispose;

    /**
     * Added raw argument. Removed Internet Explorer < 9 workaround
     *
     * @param {string} subtitle
     * @param {boolean=} raw
     * @returns {string}
     */
    Chaplin.Layout.prototype.adjustTitle = function(subtitle, raw) {
        if (!raw) {
            var title,
                _this = this;
            if (subtitle == null) {
                subtitle = '';
            }
            title = this.settings.titleTemplate({
                title: this.title,
                subtitle: subtitle
            });
        } else {
            title = subtitle;
        }
        // removed Internet Explorer < 9 workaround
        document.title = title;
        return title;
    };

    Chaplin.View.prototype.dispose = function () {
        if (this.disposed) {
            return;
        }
        this.disposePageComponents();
        this.trigger('dispose', this);
        original.viewDispose.call(this, arguments);
    };

    /**
     * Fixes issue where path '/' was converted to boolean false value
     * @override
     */
    Chaplin.Router.prototype.route = function(pathDesc, params, options) {
        var handler, path;
        if (typeof pathDesc === 'object') {
            path = pathDesc.url;
            if (!params && pathDesc.params) {
                params = pathDesc.params;
            }
        }
        params = params ? _.isArray(params) ? params.slice() : _.extend({}, params) : {};
        if (path != null) {
            path = path.replace(this.removeRoot, '');
            handler = this.findHandler(function(handler) {
                return handler.route.test(path);
            });
            options = params;
            params = null;
        } else {
            options = options ? _.extend({}, options) : {};
            handler = this.findHandler(function(handler) {
                if (handler.route.matches(pathDesc)) {
                    params = handler.route.normalizeParams(params);
                    if (params) {
                        return true;
                    }
                }
                return false;
            });
        }
        if (handler) {
            _.defaults(options, {
                changeURL: true
            });
            handler.callback(path != null ? path : params, options);
            return true;
        } else {
            throw new Error('Router#route: request was not routed');
        }
    };

    /**
     * Added force flag that allows to retrieve even stale composition
     *
     * @param {string} name
     * @param {boolean=} force
     * @returns {*}
     * @override
     */
    Chaplin.Composer.prototype.retrieve = function(name, force) {
        var active;
        active = this.compositions[name];
        if (active && (force || !active.stale())) {
            return active.item;
        } else {
            return void 0;
        }
    };

    /**
     * Fixes issue when correspondent over options regions are not taken into account
     * @override
     */
    Chaplin.Layout.prototype.registerGlobalRegions = function(instance) {
        var name, selector, version, _i, _len, _ref;
        _ref = utils.getAllPropertyVersions(instance, 'regions');

        if (instance.hasOwnProperty('regions')) {
            _ref.push(instance.regions);
        }

        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
            version = _ref[_i];
            for (name in version) {
                selector = version[name];
                this.registerGlobalRegion(instance, name, selector);
            }
        }
    };

    /**
     * Fixes issues
     *  - empty hashes (like '#')
     *  - routing full url (containing protocol and host)
     *  - stops application's navigation if it's an error page
     *  - process links with redirect options
     * @override
     */
    Chaplin.Layout.prototype.openLink = _.wrap(Chaplin.Layout.prototype.openLink, function(func, event) {
        var el, $el, href, options, payload, external, isAnchor, skipRouting, type;
        el = event.currentTarget;
        $el = $(el);

        if (event.isDefaultPrevented() || $el.parents('.sf-toolbar').length || tools.isErrorPage()) {
            return;
        }

        if (el.nodeName === 'A' && el.getAttribute('href')) {
            href = el.getAttribute('href');
            // prevent click by empty hashes
            if (href === '#') {
                event.preventDefault();
                return;
            }
            // fixes issue of routing full url
            if (href.indexOf(':\/\/') !== -1 && el.host === location.host) {
                el.setAttribute('href', el.pathname + el.search + el.hash);
            }
        }

        payload = {prevented: false, target: el};
        Chaplin.mediator.publish('openLink:before', payload);

        if (payload.prevented !== false) {
            event.preventDefault();
            return;
        }

        /* original Chaplin's openLink code: start */
        if (utils.modifierKeyPressed(event)) {
            return;
        }
        el = $ ? event.currentTarget : event.delegateTarget;
        isAnchor = el.nodeName === 'A';
        href = el.getAttribute('href') || el.getAttribute('data-href') || null;
        if (!(href != null) || href === '' || href.charAt(0) === '#') {
            return;
        }
        skipRouting = this.settings.skipRouting;
        type = typeof skipRouting;
        if (type === 'function' && !skipRouting(href, el) || type === 'string' && ($ ? $(el).is(skipRouting) : Backbone.utils.matchesSelector(el, skipRouting))) {
            return;
        }
        external = isAnchor && this.isExternalLink(el);
        if (external) {
            if (this.settings.openExternalToBlank) {
                event.preventDefault();
                window.open(href);
            }
            return;
        }
        /* original Chaplin's openLink code:end */

        // now it's possible to pass redirect options over elements data-options attribute
        options = $el.data('options') || {};
        utils.redirectTo({url: href}, options);
        event.preventDefault();
    });

    /**
     * In case it's an error page blocks application's navigation and turns on full redirect
     * @override
     */
    utils.redirectTo = _.wrap(utils.redirectTo, function (func, pathDesc, params, options) {
        if (typeof pathDesc === 'object' && pathDesc.url != null && tools.isErrorPage()) {
            options = params || {};
            options.fullRedirect = true;
            Chaplin.mediator.execute('redirectTo', pathDesc, options);
        } else {
            func.apply(this, _.rest(arguments));
        }
    });

    return Chaplin;
});
