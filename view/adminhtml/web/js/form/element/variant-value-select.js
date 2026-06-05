define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'jquery'
], function (Select, registry, $) {
    'use strict';

    return Select.extend({
        defaults: {
            optionsUrl: ''
        },

        /**
         * Subscribe to the sibling "attribute" field's value observable and load
         * that attribute's options via AJAX. (Declarative cross-field imports were
         * unreliable inside dynamicRows + ui-select here.)
         */
        initialize: function () {
            this._super();

            var self = this,
                attrName = this.parentName + '.attribute';

            registry.get(attrName, function (attrField) {
                if (attrField && attrField.value) {
                    attrField.value.subscribe(function (v) {
                        self.onAttributeChange(v);
                    });
                    if (attrField.value()) {
                        self.onAttributeChange(attrField.value());
                    }
                }
            });

            return this;
        },

        onAttributeChange: function (code) {
            var self = this;

            if (Array.isArray(code)) {
                code = code.length ? code[0] : '';
            }
            if (code && typeof code === 'object') {
                code = code.value || '';
            }
            if (!code) {
                this.setOptions([]);
                return;
            }
            $.ajax({
                url: this.optionsUrl,
                data: { attribute: code },
                dataType: 'json',
                showLoader: false
            }).done(function (res) {
                self.setOptions(Array.isArray(res) ? res : []);
            });
        }
    });
});
