define(function(require) {
    'use strict';

    const NumberFormatter = require('orofilter/js/formatter/number-formatter');

    describe('orofilter/js/formatter/number-formatter', function() {
        describe('check formatter options', function() {
            it('check default formatter', function() {
                const formatter = new NumberFormatter();

                expect(formatter.fromRaw(123456.789)).toEqual('123456.789');
                expect(formatter.toRaw('123456.789')).toEqual(123456.789);
            });

            it('check custom formatter', function() {
                const formatter = new NumberFormatter({
                    orderSeparator: ','
                });

                expect(formatter.fromRaw(123456.789)).toEqual('123,456.789');
                expect(formatter.toRaw('123,456.789')).toEqual(123456.789);
            });

            it('check percent numbers', function() {
                const formatter = new NumberFormatter({
                    percent: true
                });

                expect(formatter.toRaw('100%')).toEqual(100);
                expect(formatter.toRaw('10%')).toEqual(10);
                expect(formatter.toRaw('10.5%')).toEqual(10.5);
                expect(formatter.toRaw('1%')).toEqual(1);
                expect(formatter.toRaw('0.1%')).toEqual(0.1);

                expect(formatter.fromRaw(0.1)).toEqual('0.1%');
                expect(formatter.fromRaw(100)).toEqual('100%');
                expect(formatter.fromRaw(10)).toEqual('10%');
                expect(formatter.fromRaw(1)).toEqual('1%');
                expect(formatter.fromRaw(0.1)).toEqual('0.1%');
            });
        });
    });
});
