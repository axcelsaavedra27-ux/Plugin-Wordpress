/**
 * Bloque Gutenberg "Calculadora de cielorraso PVC" (sin paso de compilación).
 */
(function (wp) {
	'use strict';
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType('ccr/calculadora', {
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el('div', useBlockProps(),
				el(InspectorControls, null,
					el(PanelBody, { title: __('Ajustes de la calculadora', 'calculadora-cielorraso-pvc'), initialOpen: true },
						el(ToggleControl, {
							label: __('Ocultar título y subtítulo', 'calculadora-cielorraso-pvc'),
							checked: !!a.hideHeader,
							onChange: function (v) { set({ hideHeader: v }); }
						}),
						!a.hideHeader && el(TextControl, {
							label: __('Título (vacío = el de Ajustes)', 'calculadora-cielorraso-pvc'),
							value: a.title,
							onChange: function (v) { set({ title: v }); }
						}),
						!a.hideHeader && el(TextControl, {
							label: __('Subtítulo (vacío = el de Ajustes)', 'calculadora-cielorraso-pvc'),
							value: a.subtitle,
							onChange: function (v) { set({ subtitle: v }); }
						}),
						el('p', { className: 'components-base-control__help' },
							__('Materiales, fórmulas y precios se administran en el menú "Cálculos de Cielorraso".', 'calculadora-cielorraso-pvc'))
					)
				),
				el(Disabled, null, el(ServerSideRender, { block: 'ccr/calculadora', attributes: a }))
			);
		},
		save: function () { return null; }
	});
})(window.wp);
