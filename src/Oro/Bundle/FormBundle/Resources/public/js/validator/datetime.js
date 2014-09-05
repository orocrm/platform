/*global define*/
define(['underscore', 'orotranslation/js/translator', 'orolocale/js/formatter/datetime'
    ], function (_, __, datetimeFormatter) {
    'use strict';

    var defaultParam = {
        message: 'This value is not a valid date.'
    };

    /**
     * @export oroform/js/validator/datetime
     */
    return [
        'DateTime',
        function (value, element) {
            return this.optional(element) || datetimeFormatter.isDateTimeValid(String(value));
        },
        function (param, element) {
            var value = String(this.elementValue(element)),
                placeholders = {};
            param = _.extend({}, defaultParam, param);
            placeholders.value = value;
            return __(param.message, placeholders);
        }
    ];
});
