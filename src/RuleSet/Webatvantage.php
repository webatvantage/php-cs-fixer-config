<?php

namespace Webatvantage\PhpCsFixer\Config\RuleSet;

use Webatvantage\PhpCsFixer\Config\RuleSet;

final class Webatvantage
{
	public static function make(): RuleSet
	{
		return RuleSet::make(
			'webatvantage',
			[
				 '@PSR12' => true, // PSR 12 Rule set
				 'array_syntax' => ['syntax' => 'short'], // PHP arrays should be declared using the configured syntax
				 'ordered_imports' => ['sort_algorithm' => 'alpha'], // Ordering use statements.
				 'no_unused_imports' => true, // Unused use statements must be removed.
				 'not_operator_with_successor_space' => false, // Disable: Logical NOT operators (!) should have one trailing whitespace.
				 'trailing_comma_in_multiline' => [ // Argument lists, array destructuring lists, arrays that are multi-line, match-lines and the parameter lists must have a trailing comma.
					 'elements' => [
						 'arguments',
						 'array_destructuring',
						 'arrays',
						 'match',
						 'parameters',
					 ],
				 ],
				 'phpdoc_scalar' => true, // Scalar types should always be written in the same form. int not integer, bool not boolean, float not real or double.
				 'unary_operator_spaces' => true, // Unary operators should be placed adjacent to their operands.
				 'binary_operator_spaces' => true, // Binary operators should be surrounded by space as configured.
				 'control_structure_continuation_position' => [ // Control structure continuation keyword must be on the configured line.
					 'position' => 'next_line',
				 ],
				 'braces_position' => [ // Braces must be placed as configured.
					 'functions_opening_brace' => 'next_line_unless_newline_at_signature_end', // The position of the opening brace of functions‘ body.
					 'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end', // The position of the opening brace of control structures‘ body.
				 ],
				 'blank_line_before_statement' => [ // An empty line feed must precede any configured statement.
					 'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try', 'if'],
				 ],
				 'phpdoc_single_line_var_spacing' => true, // Single line @var PHPDoc should have proper spacing.
				 'phpdoc_var_without_name' => true, // @var and @type annotations of classy properties should not contain the name.
				 'general_phpdoc_annotation_remove' => [ // Removes configured annotations from PHPDoc.
					 'annotations' => ['version', 'author', 'package', 'subpackage'],
				 ],
				 'phpdoc_param_order' => true, // Orders all @param annotations in DocBlocks according to method signature.
				 'phpdoc_trim_consecutive_blank_line_separation' => true, // Removes extra blank lines after summary and after description in PHPDoc.
				 'phpdoc_separation' => [ // Annotations in PHPDoc should be grouped together so that annotations of the same type immediately follow each other. Annotations of a different type are separated by a single blank line.
					 'groups' => [
						 ['param'],
						 ['return'],
						 ['throws'],
					 ],
				 ],
				 'class_attributes_separation' => [ // Class, trait and interface elements must be separated with one or none blank line.
					 'elements' => [
						 'method' => 'one',
					 ],
				 ],
				 'method_argument_space' => [ // In method arguments and method call, there MUST NOT be a space before each comma, and there MUST be one space after each comma. Argument lists MAY be split across multiple lines, where each subsequent line is indented once. When doing so, the first item in the list MUST be on the next line, and there MUST be only one argument per line.
					 'on_multiline' => 'ensure_fully_multiline', // Defines how to handle function arguments lists that contain newlines.
					 'keep_multiple_spaces_after_comma' => true, // Whether keep multiple spaces after comma.
				 ],
				 'single_trait_insert_per_statement' => true, // Each trait use must be done as a single statement.
				 'concat_space' => [ // Concatenation should be spaced according to configuration.
					 'spacing' => 'one',
				 ],
				 'no_extra_blank_lines' => true, // Removes extra blank lines and/or blank lines following configuration.
				 'multiline_promoted_properties' => [ // Promoted properties must be on separate lines.
					 'minimum_number_of_parameters' => 3, // Minimum number of parameters in the constructor to fix.
				 ],
				 'single_line_empty_body' => true, // The empty body of class, interface, trait, enum or function must be abbreviated as {} and placed on the same line as the previous symbol, separated by a single space.
				 'type_declaration_spaces' => [ // Ensure a single space between a variable and its type declaration in function arguments and properties.
					 'elements' => [
						 'constant',
						 'function',
						 'property',
					 ],
				 ],
			 ],
		);
	}
}
