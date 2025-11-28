module.exports = {
	extends: ["@wordpress/stylelint-config", "stylelint-config-prettier"],
	rules: {
		// Additional rules if needed
		"no-descending-specificity": null,
		"font-family-no-missing-generic-family-keyword": null,
		"function-name-case": null,
		"rule-empty-line-before": [
			"always",
			{
				except: ["first-nested"],
				ignore: ["after-comment"],
			},
		],
	},
	ignoreFiles: [
		"**/*",
		"!**/wp-content/themes/bilberrry/src/**"
	],
};
