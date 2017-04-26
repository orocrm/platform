define(function(require) {
    'use strict';

    var WorkflowManagementView;
    var _ = require('underscore');
    var $ = require('jquery');
    var __ = require('orotranslation/js/translator');
    var Confirmation = require('oroui/js/delete-confirmation');
    var BaseView = require('oroui/js/app/views/base/view');
    var StepsListView = require('./step/step-list-view');
    require('oroentity/js/fields-loader');

    /**
     * @export  oroworkflow/js/workflow-management
     * @class   oro.WorkflowManagement
     * @extends Backbone.View
     */
    WorkflowManagementView = BaseView.extend({
        events: {
            'click .add-step-btn': 'addNewStep',
            'click .add-transition-btn': 'addNewTransition',
            'click .refresh-btn': 'refreshChart',
            'submit': 'onSubmit',
            'click [type=submit]': 'setSubmitActor'
        },

        options: {
            stepsEl: null,
            model: null,
            entities: [],
            entityFields: {},
            templateTranslateLink: null,
            selectorTranslateLinkContainer: '#workflow_translate_link_label'
        },

        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);

            this.initStartStepSelector();

            this.stepListView = new StepsListView({
                el: this.$(this.options.stepsEl),
                collection: this.model.get('steps'),
                workflow: this.model
            });

            this.$entitySelectEl = this.$('[name$="[related_entity]"]');

            var template = this.options.templateTranslateLink || $('#workflow-translate-link-template').html();
            this.templateTranslateLink = _.template(template);

            this.initEntityFieldsLoader(this.options.entityFields);
            this.listenTo(this.model.get('steps'), 'destroy ', this.onStepRemove);
        },

        render: function() {
            this.renderSteps();
            if (this.model.translateLinkLabel) {
                $(this.options.selectorTranslateLinkContainer)
                    .html(this.templateTranslateLink({translateLink: this.model.translateLinkLabel}));
            }

            return this;
        },

        renderSteps: function() {
            this.stepListView.render();
        },

        onSubmit: function(e) {
            this.model.trigger('saveWorkflow', e);
        },

        setSubmitActor: function(e) {
            this.submitActor = e.target;
        },

        initStartStepSelector: function() {
            var getSteps = _.bind(function(query) {
                var steps = [];
                _.each(this.model.get('steps').models, function(step) {
                    // starting point is not allowed to be a start step
                    var stepLabel = step.get('label');
                    if (!step.get('_is_start') &&
                        (!query.term || query.term === stepLabel || _.indexOf(stepLabel, query.term) !== -1)
                    ) {
                        steps.push({
                            'id': step.get('name'),
                            'text': step.get('label')
                        });
                    }
                }, this);

                query.callback({results: steps});
            }, this);

            this.$startStepEl = this.$('[name="start_step"]');

            var select2Options = {
                'allowClear': true,
                'query': getSteps,
                'placeholder': __('Choose step...'),
                'initSelection': _.bind(function(element, callback) {
                    var startStep = this.model.getStepByName(element.val());
                    callback({
                        id: startStep.get('name'),
                        text: startStep.get('label')
                    });
                }, this)
            };

            this.$startStepEl.inputWidget('create', 'select2', {initializeOptions: select2Options});
        },

        /**
         * @param {Object} entityFields
         */
        initEntityFieldsLoader: function(entityFields) {
            var confirm = new Confirmation({
                title: __('Change Entity Confirmation'),
                okText: __('Yes'),
                content: __('oro.workflow.change_entity_confirmation')
            });
            confirm.on('ok', _.bind(function() {
                this.model.set('entity', this.$entitySelectEl.val());
            }, this));
            confirm.on('cancel', _.bind(function() {
                this.$entitySelectEl.inputWidget('val', this.model.get('entity'));
            }, this));

            this.$entitySelectEl.fieldsLoader({
                router: 'oro_api_workflow_entity_get',
                routingParams: {},
                confirm: confirm,
                requireConfirm: _.bind(this._requireConfirm, this)
            });
            this.$entitySelectEl.fieldsLoader('setFieldsData', entityFields);

            this.$entitySelectEl.on('change', _.bind(function() {
                if (!this._requireConfirm()) {
                    this.model.set('entity', this.$entitySelectEl.val());
                }
            }, this));

            this.$entitySelectEl.on('fieldsloadercomplete', _.bind(function(e) {
                this.initEntityFieldsData($(e.target).data('fields'));
            }, this));

            this._preloadEntityFieldsData();
        },

        _requireConfirm: function() {
            return (this.model.get('steps').length +
                this.model.get('transitions').length +
                this.model.get('transition_definitions').length +
                this.model.get('attributes').length) > 1;
        },

        _preloadEntityFieldsData: function() {
            if (this.$entitySelectEl.val()) {
                var fieldsData = this.$entitySelectEl.fieldsLoader('getFieldsData');
                if (_.isEmpty(fieldsData)) {
                    this.$entitySelectEl.fieldsLoader('loadFields');
                } else {
                    this.initEntityFieldsData(fieldsData);
                }
            }
        },

        addNewTransition: function() {
            this.model.trigger('requestAddTransition');
        },

        addNewStep: function() {
            this.model.trigger('requestAddStep');
        },

        refreshChart: function() {
            this.model.trigger('requestRefreshChart');
        },

        initEntityFieldsData: function(fields) {
            this.model.setEntityFieldsData(fields);
        },

        onStepRemove: function(step) {
            //Deselect start_step if it was removed
            if (this.$startStepEl.val() === step.get('name')) {
                this.$startStepEl.inputWidget('val', '');
            }
        },

        isEntitySelected: function() {
            return Boolean(this.$entitySelectEl.val());
        },

        getEntitySelect: function() {
            return this.$entitySelectEl;
        },

        valid: function() {
            return this.$el.valid();
        }
    });

    return WorkflowManagementView;
});
