/*jslint browser: true, vars: true, nomen: true*/
/*jshint browser: true, devel: true*/
/*global define*/
define(function (require) {
    'use strict';

    var $ = require('jquery');
    var _ = require('underscore');
    var Backbone = require('backbone');
    var __ = require('orotranslation/js/translator');
    var app = require('oroui/js/app');
    var mediator = require('oroui/js/mediator');
    var messenger = require('oroui/js/messenger');
    var Modal = require('oroui/js/modal');
    var LoadingMask = require('oroui/js/loading-mask');
    var PagestateView = require('./pagestate/view');
    var PagestateModel = require('./pagestate/model');
    var PageableCollection = require('orodatagrid/js/pageable-collection');
    var widgetManager = require('oroui/js/widget-manager');
    var contentManager = require('./content-manager');
    var _jqueryForm = require('jquery.form');

    var Navigation;
    var instance;
    var pinbarView = null;
    var pageCacheStates = {
        state: {},

        saveObjectCache: function (key, state) {
            this.state[key] = this.state[key] || {};
            _.extend(this.state[key], state);
        },

        getObjectCache: function (key) {
            return this.state[key] || {};
        }
    };

    /**
     * Router for hash navigation
     *
     * @_export  oronavigation/js/navigation
     * @class   oronavigation
     * @extends Backbone.Router
     */
    Navigation = Backbone.Router.extend({
        /**
         * Hash navigation enabled/disabled flag
         */
        enabled: true,

        /**
         * links - Selector for all links that will be processed by hash navigation
         * scrollLinks - Selector for anchor links
         * content - Selector for ajax response content area
         * container - Selector for main content area
         * loadingMask - Selector for loading spinner
         * searchDropdown - Selector for dropdown with search results
         * menuDropdowns - Selector for 3 dots menu and user dropdowns
         * pinbarHelp - Selector for pinbars help link
         * historyTab - Selector for history 3 dots menu tab
         * mostViwedTab - Selector for most viewed 3 dots menu tab
         * flashMessages - Selector for system messages block
         * menu - Selector for system main menu
         * breadcrumb - Selector for breadcrumb block
         * pinButton - Selector for pin, close and favorite buttons div
         *
         * @property
         */
        selectors: {
            links:               'a:not([href^=#],[href^=javascript],[href^=mailto],[href^=skype],[href^=ftp],[href^=callto],[href^=tel]),span[data-url]',
            scrollLinks:         'a[href^=#]',
            content:             '#content',
            userMenu:            '#top-page .user-menu',
            container:           '#container',
            loadingMask:         '.hash-loading-mask',
            searchDropdown:      '#search-div',
            menuDropdowns:       '.pin-menus.dropdown, .nav .dropdown',
            pinbarHelp:          '.pin-bar-empty',
            historyTab:          '#history-content',
            mostViewedTab:       '#mostviewed-content',
            flashMessages:       '#flash-messages',
            menu:                '#main-menu',
            breadcrumb:          '#breadcrumb',
            pinButtonsContainer: '#pin-button-div',
            gridContainer:       '.grid-container',
            pinButtons:          '.minimize-button, .favorite-button'
        },

        /**
         * Cached jQuery objects by selectors from selectors property
         * @property {Object}
         */
        _selectorCached: {},

        /**
         * @property {oro.LoadingMask}
         */
        loadingMask: '',

        /**
         * @property {string}
         */
        baseUrl: '',

        /**
         * @property {string}
         */
        headerId: '',

        /**
         * @property {Object}
         */
        headerObject: {},

        /**
         * State data for grids
         *
         * @property
         */
        encodedStateData: '',

        /**
         * Url part
         *
         * @property
         */
        url: '',

        /** @property {oro.datagrid.Router} */
        gridRoute: '',

        /** @property */
        routes: {
            "(url=*page)(|g/*encodedStateData)": "defaultAction",
            "g/*encodedStateData": "gridChangeStateAction"
        },

        /**
         * Flag whether to use states cache for current page load
         */
        useCache: false,

        skipAjaxCall: false,

        skipGridStateChange: false,

        maxCachedPages: 10,

        tempCache: '',

        formState: '',

        confirmModal: null,

        /**
         * Initialize hash navigation
         *
         * @param options
         */
        initialize: function (options) {
            if (!options.baseUrl || !options.headerId) {
                throw new TypeError("'baseUrl' and 'headerId' are required");
            }

            this.baseUrl =  options.baseUrl;
            this.headerId = options.headerId;
            this.headerObject[this.headerId] = true;
            this.url = this.getHashUrl();
            if (!window.location.hash) {
                //skip ajax page refresh for the current page
                this.skipAjaxCall = true;
            }

            this.init();
            contentManager.init(this.url, options.userName || false);

            Backbone.Router.prototype.initialize.apply(this, arguments);
        },

        isMaintenancePage: function(){
            var metaError = $('meta[name="error"]');
            return metaError.length && metaError.attr('content') == 503;
        },

        /**
         * Returns cached jQuery object by name
         * @param name
         * @returns {Object}
         */
        getCached$: function (name) {
            var selectors = this._selectorCached;

            if (!selectors[name]) {
                selectors[name] = $(this.selectors[name]);
            }

            return selectors[name];
        },

        /**
         * Init
         */
        init: function() {
            /**
             * Processing all links in grid after grid load
             */
            mediator.bind("grid_load:complete", function (collection) {
                this.updateCachedContent(collection.inputName, {'collection': collection});
                if (pinbarView) {
                    var item = pinbarView.getItemForCurrentPage(true);
                    if (item.length && this.useCache) {
                        contentManager.addPage(this.getHashUrl(), this.tempCache);
                    }
                }
                this.processGridLinks();
            }, this);

            /**
             * Loading grid collection from cache
             */
            mediator.bind("datagrid_collection_set_before", function (payload) {
                var gridName = payload.name,
                    data = this.getCachedData();
                if (data.states) {
                    var girdState = data.states.getObjectCache(gridName);
                    if (girdState.collection) {
                        payload.collection = girdState.collection.clone();
                    }
                }
            }, this);

            /**
             * Updating grid collection in cache
             */
            mediator.bind("datagrid_collection_set_after", function (collection) {
                var gridName = collection.inputName,
                    data = this.getCachedData();
                if (data.states) {
                    var girdState = data.states.getObjectCache(gridName);
                    girdState.collection = collection;
                } else { //updating temp cache with collection
                    this.updateCachedContent(gridName, {collection: collection});
                }
            }, this);

            /**
             * Trigger updateState event for grid collection if page was loaded from cache
             */
            mediator.bind("datagrid_filters:rendered", function (collection) {
                if (this.getCachedData() && this.encodedStateData) {
                    collection.trigger('updateState', collection);
                }
            }, this);

            /**
             * Clear page cache for unpinned page
             */
            mediator.bind("pinbar_item_remove_before", function (item) {
                var url = this.removeGridParams(item.get('url'));
                contentManager.clearCache(url);
            }, this);

            /**
             * Add "pinned" page to cache
             */
            mediator.bind("pinbar_item_minimized", function () {
                this.useCache = true;
                contentManager.addPage(this.getHashUrl(), this.tempCache);
            }, this);

            /**
             * Add "pinned" page to cache
             */
            mediator.bind("pagestate_collected", function (pagestateModel) {
                this.updateCachedContent('form', {formData: pagestateModel.get('pagestate').data});
                if (this.useCache) {
                    contentManager.addPage(this.getHashUrl(), this.tempCache);
                }
            }, this);

            /**
             * Processing navigate action execute
             */
            mediator.bind("grid_action:navigateAction:preExecute", function (action, options) {
                this.setLocation(action.getLink());
                options.doExecute = false;
            }, this);

            /**
             * Checking for grid route and updating it's state
             */
            mediator.bind("grid_route:loaded", function (route) {
                this.gridRoute = route;
                if (!this.skipGridStateChange) {
                    this.gridChangeState();
                }
                this.processGridLinks();
            }, this);

            /**
             * Add processing links of loaded widget content
             */
            mediator.bind("widget:contentLoad", function (widgetEl) {
                this.processClicks(widgetEl.find(this.selectors.links));
            }, this);

            /**
             * Processing links in 3 dots menu after item is added (e.g. favourites)
             */
            mediator.bind("navigaion_item:added", function (item) {
                this.processClicks(item.find(this.selectors.links));
            }, this);

            /**
             * Processing links in search result dropdown
             */
            mediator.bind("top_search_request:complete", function () {
                this.processClicks($(this.getCached$('searchDropdown')).find(this.selectors.links));
            }, this);

            /**
             * Processing pinbar help link
             */
            mediator.bind("pinbar_help:shown", function () {
                this.processClicks(this.selectors.pinbarHelp);
            }, this);

            this.confirmModal = new Modal({
                title: __('Refresh Confirmation'),
                content: __('Your local changes will be lost. Are you sure you want to refresh the page?'),
                okText: __('Ok, got it.'),
                className: 'modal modal-primary',
                okButtonClass: 'btn-primary btn-large',
                cancelText: __('Cancel')
            });
            this.confirmModal.on('ok', _.bind(function() {
                this.refreshPage();
            }, this));

            $(document).on('click.action.data-api', '[data-action=page-refresh]', _.bind(function(e) {
                var formState, data = this.getCachedData();
                e.preventDefault();
                if (data.states) {
                    formState = data.states.getObjectCache('form');
                    /**
                     *  saving form state for future restore after content refresh, uncomment after new page states logic is
                     *  implemented
                     */
                    //this.formState = formState;
                }
                if (formState && formState.formData.length) {
                    this.confirmModal.open();
                } else {
                    this.refreshPage();
                }
            }, this));

            /**
             * Processing all links
             */
            this.processClicks(this.getCached$('links'));
            this.disableEmptyLinks(this.getCached$('menu').find(this.selectors.scrollLinks));

            this.processForms(this.selectors.forms);
            this.processAnchors(this.getCached$('container').find(this.selectors.scrollLinks));

            this.loadingMask = new LoadingMask();
            this.renderLoadingMask();
        },

        /**
         * Routing default action
         *
         * @param {String} page
         * @param {String} encodedStateData
         */
        defaultAction: function(page, encodedStateData) {
            this.beforeAction();
            this.beforeDefaultAction();
            this.encodedStateData = encodedStateData;
            this.url = page;
            if (!this.url) {
                this.url = window.location.href.replace(this.baseUrl, '');
            }
            if (!this.skipAjaxCall) {
                this.loadPage();
            }
            this.skipAjaxCall = false;
        },

        /**
         * Before any navigation changes triggers event
         */
        beforeAction: function() {
            mediator.trigger("hash_navigation_request:before", this);
        },

        /**
         * Shows that content changing is in a process
         * @returns {boolean}
         */
        isInAction: function() {
            return this.loadingMask.displayed;
        },

        beforeDefaultAction: function() {
            //reset pagestate restore flag in case we left the page
            if (this.url !== this.getHashUrl(false, true)) {
                this.getPagestate().needServerRestore = true;
            }
        },

        /**
         * Routing grid state changed action
         *
         * @param encodedStateData
         */
        gridChangeStateAction: function(encodedStateData) {
            this.encodedStateData = encodedStateData;
        },

        /**
         *  Changing state for grid
         */
        gridChangeState: function() {
            if (!this.getCachedData() && this.gridRoute && this.encodedStateData && this.encodedStateData.length) {
                this.gridRoute.changeState(this.encodedStateData);
            }
        },

        getPagestate: function() {
            if (!this.pagestate) {
                this.pagestate = new PagestateView({
                    model: new PagestateModel()
                });
            }
            return this.pagestate;
        },

        /**
         * Ajax call for loading page content
         */
        loadPage: function(forceLoad) {
            forceLoad = forceLoad || false;
            if (!this.url) {
                return;
            }

            this.beforeRequest();

            var cacheData = this.getCachedData();
            if (!forceLoad && cacheData) {
                widgetManager.resetWidgets();
                this.tempCache = cacheData;
                this.handleResponse(cacheData, {fromCache: true});
                this.afterRequest();
                return;
            }

            var pageUrl = this.baseUrl + this.url;
            var stringState = [];
            this.skipGridStateChange = false;
            if (this.encodedStateData) {
                var state = PageableCollection.prototype.decodeStateData(this.encodedStateData);
                var collection = new PageableCollection({}, {inputName: state.gridName});

                stringState = collection.processQueryParams({}, state);
                stringState = collection.processFiltersParams(stringState, state);

                mediator.once(
                    "datagrid_filters:rendered",
                    function (collection) {
                        collection.trigger('updateState', collection);
                    },
                    this
                );

                this.skipGridStateChange = true;
            }

            var useCache = this.useCache;
            $.ajax({
                url: pageUrl,
                headers: this.headerObject,
                data: stringState,
                beforeSend: function( xhr ) {
                    $.isActive(false);
                    //remove standard ajax header because we already have a custom header sent
                    xhr.setRequestHeader('X-Requested-With', {toString: function(){ return ''; }});
                },

                error: _.bind(this.processError, this),

                success: _.bind(function (data, textStatus, jqXHR) {
                    this.handleResponse(data);
                    this.updateDebugToolbar(jqXHR);
                    this.afterRequest();
                    if (useCache) {
                        contentManager.addPage(this.getHashUrl(), this.tempCache);
                    }
                }, this)
            });
        },

        /**
         * Restore form state from cache
         *
         * @param cacheData
         */
        restoreFormState: function(cacheData) {
            var formState = {},
                pagestate = this.getPagestate();
            if (this.formState) {
                formState = this.formState;
            } else if (cacheData.states) {
                formState = cacheData.states.getObjectCache('form');
            }
            if (formState.formData && formState.formData.length) {
                pagestate.updateState(formState.formData);
                pagestate.restore();
                pagestate.needServerRestore = false;
            }
        },

        /**
         * Update debug toolbar.
         *
         * @param jqXHR
         */
        updateDebugToolbar: function(jqXHR) {
            var debugBarToken = jqXHR.getResponseHeader('x-debug-token');
            var entryPoint = window.location.pathname;
            if (entryPoint.indexOf('.php') !== -1) {
                entryPoint = entryPoint.substr(0, entryPoint.indexOf('.php') + 4);
            }
			if (entryPoint[entryPoint.length-1] != '/') {
                entryPoint += '/';
            }
            if(debugBarToken) {
                var url = entryPoint + '_wdt/' + debugBarToken;
                $.get(
                    this.baseUrl + url,
                    _.bind(function(data) {
                        var dtContainer = $('<div class="sf-toolbar" id="sfwdt' + debugBarToken + '" style="display: block;" data-sfurl="' + url + '"/>');
                        dtContainer.html(data);
                        var scrollable = $('.scrollable-container:last');
                        var container = scrollable.length ? scrollable : this.getCached$('container');
                        if (!container.closest('body').length) {
                            container = $(document.body);
                        }
                        $('.sf-toolbar').remove();
                        container.append(dtContainer);
                        mediator.trigger('layout:adjustHeight');
                    }, this)
                );
            }
        },

        /**
         * Save page content to temp cache
         *
         * @param data
         */
        savePageToCache: function(data) {
            this.tempCache = {};
            this.tempCache = _.clone(data);
            this.tempCache.states = app.deepClone(pageCacheStates);
        },

        /**
         * Get cache data for url
         *
         * @param {string=} url
         * @return {*}
         */
        getCachedData: function(url) {
            return contentManager.getPage(_.isUndefined(url) ? this.getHashUrl() : url);
        },

        /**
         * Save page content to cache
         *
         * @param key
         * @param state
         */
        updateCachedContent: function (key, state) {
            if (this.tempCache.states) {
                this.tempCache.states.saveObjectCache(key, state);
            }
        },

        showLoading: function() {
            if (this.loadingMask) {
                this.loadingMask.show();
            }
        },

        hideLoading: function() {
            if (this.loadingMask) {
                this.loadingMask.hide();
            }
        },

        /**
         *  Triggered before hash navigation ajax request
         */
        beforeRequest: function() {
            this.showLoading();
            this.gridRoute = ''; //clearing grid router
            this.tempCache = '';
            /**
             * Backbone event. Fired before navigation ajax request is started
             * @event hash_navigation_request:start
             */
            mediator.trigger("hash_navigation_request:start", this);
        },

        /**
         *  Triggered after hash navigation ajax request
         */
        afterRequest: function() {
            this.formState = '';
        },

        /**
         * Renders loading mask.
         *
         * @protected
         */
        renderLoadingMask: function() {
            this.getCached$('loadingMask').append(this.loadingMask.render().$el);
            this.hideLoading();
        },

        refreshPage: function() {
            contentManager.clearCache(this.url);
            this.loadPage();
            mediator.trigger("hash_navigation_request:page_refreshed", { url: this.url, navigationInstance: this});
        },

        /**
         * Clearing content area with native js, prevents freezing of firefox with firebug enabled.
         * If no container found, reload the page
         */
        clearContainer: function() {
            var container = document.getElementById('container');
            if (container) {
                container.innerHTML = '';
            } else {
                location.reload();
            }
        },

        /**
         * Remove grid state params from url
         * @param url
         */
        removeGridParams: function(url) {
            return url.split('#g')[0];
        },

        /**
         * Make data more bulletproof.
         *
         * @param {String} rawData
         * @returns {Object}
         * @param prevPos
         */
        getCorrectedData: function(rawData, prevPos) {
            if (_.isUndefined(prevPos)) {
                prevPos = -1;
            }
            rawData = $.trim(rawData);
            var jsonStartPos = rawData.indexOf('{', prevPos + 1);
            var additionalData = '';
            var dataObj = null;
            if (jsonStartPos > 0) {
                additionalData = rawData.substr(0, jsonStartPos);
                var data = rawData.substr(jsonStartPos);
                try {
                    dataObj = $.parseJSON(data);
                } catch (err) {
                    return this.getCorrectedData(rawData, jsonStartPos);
                }
            } else if (jsonStartPos === 0) {
                dataObj = $.parseJSON(rawData);
            } else {
                throw "Unexpected content format";
            }

            if (additionalData) {
                additionalData = '<div class="alert alert-info fade in top-messages"><a class="close" data-dismiss="alert" href="#">&times;</a>'
                    + '<div class="message">' + additionalData + '</div></div>';
            }

            if (dataObj.content !== undefined) {
                dataObj.content = additionalData + dataObj.content;
            }

            return dataObj;
        },

        /**
         * Handling ajax response data. Updating content area with new content, processing title and js
         *
         * @param {String} rawData
         * @param options
         */
        handleResponse: function (rawData, options) {
            if (_.isUndefined(options)) {
                options = {};
            }
            try {
                var data = rawData;
                if (!options.fromCache) {
                    data = (rawData.indexOf('http') === 0) ? {'redirect': true, 'fullRedirect': true, 'location': rawData} : this.getCorrectedData(rawData);
                }
                if (_.isObject(data)) {
                    if (data.redirect !== undefined && data.redirect) {
                        this.processRedirect(data);
                    } else {
                        this.removeErrorClass();

                        if (!options.fromCache && !options.skipCache) {
                            this.savePageToCache(data);
                        }
                        this.clearContainer();
                        var content = data.content;
                        this.getCached$('container').html(content);
                        this.getCached$('menu').html(data.mainMenu);
                        this.getCached$('userMenu').html(data.userMenu);
                        this.getCached$('breadcrumb').html(data.breadcrumb);
                        /**
                         * Collecting javascript from head and append them to content
                         */
                        if (data.scripts.length) {
                            this.getCached$('container').append(data.scripts);
                        }
                        /**
                         * Setting page title
                         */
                        document.title = data.title;
                        this.processClicks(this.getCached$('menu').find(this.selectors.links));
                        this.processClicks(this.getCached$('userMenu').find(this.selectors.links));
                        this.disableEmptyLinks(this.getCached$('menu').find(this.selectors.scrollLinks));
                        this.processClicks(this.getCached$('container').find(this.selectors.links));
                        this.processAnchors(this.getCached$('container').find(this.selectors.scrollLinks));
                        this.processPinButton(data);
                        this.restoreFormState(this.tempCache);
                        if (!options.fromCache) {
                            this.updateMenuTabs(data);
                            this.addMessages(data.flashMessages);
                        }
                        this.hideActiveDropdowns();
                        mediator.trigger("hash_navigation_request:refresh", this);
                        this.hideLoading();
                    }
                }
            }
            catch (err) {
                if (!_.isUndefined(console)) {
                    console.error(err);
                }
                if (app.debug) {
                    document.body.innerHTML = rawData;
                } else {
                    messenger.notificationMessage('error', __('Sorry, page was not loaded correctly'));
                    this.hideLoading();
                }
            }
            this.triggerCompleteEvent();
        },

        /**
         * Disable # links to prevent hash changing
         *
         * @param selector
         */
        disableEmptyLinks: function(selector) {
            $(selector).on('click', function(e) {
                e.preventDefault();
            });
        },

        processGridLinks: function()
        {
            this.processClicks($(this.selectors.gridContainer).find(this.selectors.links));
        },

        processRedirect: function (data) {
            var redirectUrl = data.location;
            var urlParts = redirectUrl.split('url=');
            if (urlParts[1]) {
                redirectUrl = urlParts[1];
            }
            $.isActive(true);
            if(data.fullRedirect) {
                var delimiter = '?';
                if (redirectUrl.indexOf(delimiter) !== -1) {
                    delimiter = '&';
                }
                window.location.replace(redirectUrl + delimiter + '_rand=' + Math.random());
            } else {
                //clearing cache for current and redirect urls, e.g. form and grid page
                contentManager.clearCache(this.url);
                this.setLocation(redirectUrl, {clearCache: true, useCache: this.getCachedData(redirectUrl) !== false});
            }
        },

        /**
         * Show error message
         *
         * @param {XMLHttpRequest} XMLHttpRequest
         * @param {String} textStatus
         * @param {String} errorThrown
         */
        processError: function(XMLHttpRequest, textStatus, errorThrown) {
            if (app.debug) {
                this.updateDebugToolbar(XMLHttpRequest);
            }

            this.handleResponse(XMLHttpRequest.responseText);
            this.addErrorClass();
            this.hideLoading();
        },

        /**
         * Hide active dropdowns
         */
        hideActiveDropdowns: function() {
            this.getCached$('searchDropdown').removeClass('header-search-focused');
            this.getCached$('menuDropdowns').removeClass('open');
        },

        /**
         * Add session messages
         *
         * @param messages
         */
        addMessages: function(messages) {
            this.getCached$('flashMessages').find('.flash-messages-holder').empty();
            _.each(messages, function (messages, type) {
                _.each(messages, function (message) {
                    messenger.notificationFlashMessage(type, message);
                });
            });
        },

        /**
         * View / hide pins div and set titles
         *
         * @param showPinButton
         */
        processPinButton: function(data) {
            if (data.showPinButton) {
                this.getCached$('pinButtonsContainer').show();
                /**
                 * Setting serialized titles for pinbar and favourites buttons
                 */
                var titleSerialized = data.titleSerialized;
                if (titleSerialized) {
                    titleSerialized = $.parseJSON(titleSerialized);
                    this.getCached$('pinButtonsContainer').find(this.selectors.pinButtons).data('title', titleSerialized);
                }
                this.getCached$('pinButtonsContainer').find(this.selectors.pinButtons).data('title-rendered-short', data.titleShort);
            } else {
                this.getCached$('pinButtonsContainer').hide();
            }
        },

        /**
         * Update History and Most Viewed menu tabs
         *
         * @param data
         */
        updateMenuTabs: function(data) {
            this.getCached$('historyTab').html(data.history);
            this.getCached$('mostViewedTab').html(data.mostviewed);
            /**
             * Processing links for history and most viewed tabs
             */
            this.processClicks(this.getCached$('historyTab').find(this.selectors.links));
            this.processClicks(this.getCached$('mostViewedTab').find(this.selectors.links));
        },

        /**
         * Trigger hash navigation complete event
         */
        triggerCompleteEvent: function() {
            /**
             * Backbone event. Fired when hash navigation ajax request is complete
             * @event hash_navigation_request:complete
             */
            mediator.trigger("hash_navigation_request:complete", this);
        },

        /**
         * Processing all links in selector and setting necessary click handler
         * links with "no-hash" class are not processed
         *
         * @param {String} selector
         */
        processClicks: function(selector) {
            $(selector).not('.no-hash').on('click', _.bind(function (e) {
                if (e.shiftKey || e.ctrlKey || e.metaKey || e.which === 2) {
                    return true;
                }
                var target = e.currentTarget;
                e.preventDefault();
                var link = '';
                if ($(target).is('a')) {
                    link = $(target).attr('href');
                } else if ($(target).is('span')) {
                    link = $(target).attr('data-url');
                }
                if (link) {
                    var event = {stoppedProcess: false, hashNavigationInstance: this, link: link};
                    mediator.trigger("hash_navigation_click", event);
                    if (event.stoppedProcess === false) {
                        this.setLocation(link);
                    }
                }
                return false;
            }, this));
        },

        /**
         * Manually process anchors to prevent changing urls hash. If anchor doesn't have click events attached assume it
         * a standard anchor and emulate browser anchor scroll behaviour
         *
         * @param selector
         */
        processAnchors: function(selector) {
            $(selector).each(function() {
                var href = $(this).attr('href');
                var $href = /^#\w/.test(href) && $(href);
                if ($href) {
                    var events = $._data($(this).get(0), 'events');
                    if (_.isUndefined(events) || !events.click) {
                        $(this).on('click', function (e) {
                            e.preventDefault();
                            //finding parent div with scroll
                            var scrollDiv = $href.parents().filter(function() {
                                return $(this).get(0).scrollHeight > $(this).innerHeight();
                            });
                            if (!scrollDiv) {
                                scrollDiv = $(window);
                            } else {
                                scrollDiv = scrollDiv.eq(0);
                            }
                            scrollDiv.scrollTop($href.position().top + scrollDiv.scrollTop());
                            $(this).blur();
                        });
                    }
                }
            });
        },

        /**
         * Processing forms submit events
         */
        processForms: function() {
            $('body').on('submit', _.bind(function (e) {
                var $form = $(e.target);
                if ($form.data('nohash') || e.isDefaultPrevented()) {
                    return;
                }
                e.preventDefault();
                if ($form.data('sent')) {
                    return;
                }

                var url = $form.attr('action');
                this.method = $form.attr('method') || "get";

                if (url) {
                    var formStartSettings = {
                        form_validate: true
                    };
                    mediator.trigger('hash_navigation_request:form-start', $form.get(0), formStartSettings);
                    if (formStartSettings.form_validate) {
                        $form.data('sent', true);
                        var data = $form.serialize();
                        if (this.method === 'get') {
                            if (data) {
                                url += '?' + data;
                            }
                            this.setLocation(url);
                            $form.removeData('sent');
                        } else {
                            this.beforeRequest();
                            $form.ajaxSubmit({
                                data: this.headerObject,
                                headers: this.headerObject,
                                complete: function(){
                                    $form.removeData('sent');
                                },
                                error: _.bind(this.processError, this),
                                success: _.bind(function (data) {
                                    this.handleResponse(data, {'skipCache' : true}); //don't cache form submit response
                                    this.afterRequest();
                                }, this)
                            });
                        }
                    }
                }
                return false;
            }, this));
        },

        /**
         * Returns real url part from the hash
         * @param  {boolean=} includeGrid
         * @param  {boolean=} useRaw
         * @return {string}
         */
        getHashUrl: function(includeGrid, useRaw) {
            var url = this.url;
            if (!url || useRaw) {
                if (Backbone.history.fragment) {
                    /**
                     * Get real url part from the hash without grid state
                     */
                    var urlParts = Backbone.history.fragment.split('|g/');
                    url = urlParts[0].replace('url=', '');
                    if (urlParts[1] && (!_.isUndefined(includeGrid) && includeGrid === true)) {
                        url += '#g/' + urlParts[1];
                    }
                }
                if (!url) {
                    url = window.location.pathname + window.location.search;
                }
            }
            return url;
        },

        /**
         * Check if url is a 3d party link
         *
         * @param url
         * @return {Boolean}
         */
        checkThirdPartyLink: function(url) {
            var external = new RegExp('^(https?:)?//(?!' + location.host + ')');
            return (url.indexOf('http') !== -1) && external.test(url);
        },

        /**
         * Change location hash with new url
         *
         * @param {String} url
         * @param options
         */
        setLocation: function(url, options) {
            if (_.isUndefined(options)) {
                options = {};
            }
            if (this.enabled && !this.checkThirdPartyLink(url)) {
                if (options.clearCache) {
                    contentManager.clearCache(url);
                }
                this.useCache = false;
                if (options.useCache) {
                    this.useCache = options.useCache;
                }
                url = url.replace(this.baseUrl, '').replace(/^(#\!?|\.)/, '');
                if (pinbarView) {
                    var item = pinbarView.getItemForPage(url, true);
                    if (item.length) {
                        url = item[0].get('url');
                    }
                }
                url = url.replace('#g/', '|g/');
                if (url === this.getHashUrl() && !this.encodedStateData) {
                    this.loadPage();
                } else {
                    window.location.hash = '#url=' + url;
                }
            } else {
                window.location.href = url;
            }
        },

        /**
         * @return {Boolean}
         */
        checkHashForUrl: function() {
            return window.location.hash.indexOf('#url=') !== -1;
        },

        /**
         * Processing back clicks
         *
         * @return {Boolean}
         */
        back: function() {
            window.history.back();
            return true;
        },

        /**
         * Adds error class to body
         *
         * @return {Boolean}
         */
        addErrorClass: function() {
            $('body').addClass('error-page');
            return true;
        },

        /**
         * Removes error class from body
         *
         * @return {Boolean}
         */
        removeErrorClass: function() {
            $('body').removeClass('error-page');
            return true;
        }
    });

    /**
     * Fetches flag - hash navigation is enabled or not
     *
     * @returns {boolean}
     */
    Navigation.isEnabled = function() {
        return Boolean(Navigation.prototype.enabled);
    };

    /**
     * Fetches navigation (Oro router) instance
     *
     * @returns {oronavigation.Navigation}
     */
    Navigation.getInstance = function() {
        return instance;
    };

    /**
     * Creates navigation instance
     *
     * @param {Object} options
     */
    Navigation.setup = function(options) {
        instance = new Navigation(options);
    };

    /**
     * Register Pinbar view instance
     *
     * @param {Object} pinbarView
     */
    Navigation.registerPinbarView = function (instance) {
        pinbarView = instance;
    };

    return Navigation;
});
