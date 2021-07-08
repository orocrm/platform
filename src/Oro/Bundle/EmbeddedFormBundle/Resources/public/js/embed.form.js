/* eslint no-var: off */
var ORO = (function(ORO) {
    'use strict';

    var IframeEmbeddedForm = function(container, options) {
        this.container = document.getElementById(container);
        this.options = options || {};
    };

    IframeEmbeddedForm.prototype = {
        show: function() {
            var iframe = document.createElement('iframe');
            iframe.src = this.options.src;
            iframe.width = this.options.width;
            iframe.height = this.options.height;
            iframe.frameBorder = this.options.frameBorder;

            this.container.innerHTML = '';
            this.container.appendChild(iframe);
        }
    };

    var XHREmbeddedForm = function(container, options) {
        this.container = document.getElementById(container);
        this.options = options || {};
    };

    XHREmbeddedForm.prototype = {
        ajax: function(url, settings) {
            var context = this;
            var xhr = new XMLHttpRequest();
            xhr.withCredentials = true;

            xhr.addEventListener('error', function(e) {
                settings.error.apply(context, [xhr, xhr.statusText]);
            });

            xhr.addEventListener('load', function(e) {
                if (this.status >= 200 && this.status < 400) {
                    settings.success.apply(context, [this.response, xhr.statusText, xhr]);
                } else {
                    settings.error.apply(context, [xhr, xhr.statusText]);
                }
            });

            xhr.open(settings.method, url, true);

            if (settings.hasOwnProperty('data')) {
                xhr.send(settings.data);
            } else {
                xhr.send();
            }

            return xhr;
        },

        show: function() {
            this.ajax(this.options.url, {
                method: 'GET',
                success: this.renderForm
            });
        },

        renderForm: function(data) {
            this.container.innerHTML = data;
            var form = this.container.querySelector('form');
            if (form) {
                form.addEventListener('submit', this.onSubmit.bind(this));
            }

            var scripts = this.container.querySelectorAll('script');
            if (scripts.length > 0) {
                var scriptsFragment = document.createDocumentFragment();
                var realTag;
                for (var k in scripts) {
                    if (!scripts.hasOwnProperty(k)) {
                        continue;
                    }

                    realTag = document.createElement('script');
                    for (var i = 0; i < scripts[k].attributes.length; i++) {
                        realTag.setAttribute(scripts[k].attributes[i].name, scripts[k].attributes[i].value);
                    }

                    if (!scripts[k].hasAttribute('src')) {
                        realTag.innerHTML = scripts[k].innerHTML;
                    }

                    scripts[k].parentNode.removeChild(scripts[k]);
                    scriptsFragment.appendChild(realTag);
                }
                this.container.appendChild(scriptsFragment);
            }
        },

        onSubmit: function(e) {
            e.preventDefault();

            var button = this.container.querySelector('button');
            if (button) {
                button.disabled = true;
            }

            this.ajax(this.options.url, {
                method: 'POST',
                data: new FormData(e.target),
                success: this.renderForm
            });
        }
    };

    ORO.EmbedForm = function(options) {
        var embeddedForm;
        if (options.hasOwnProperty('iframe')) {
            embeddedForm = new IframeEmbeddedForm(options.container, options.iframe);
            embeddedForm.show();

            return;
        }

        if (options.hasOwnProperty('xhr')) {
            embeddedForm = new XHREmbeddedForm(options.container, options.xhr);
            embeddedForm.show();

            return;
        }

        throw new Error('Invalid embedded form options');
    };

    return ORO;
})(ORO || {});
