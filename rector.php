<?php

declare(strict_types = 1);

use Rector\CodingStyle\Rector as coding_style;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector as dead_code;
use Rector\Php71\Rector as php_71;
use Rector\TypeDeclaration\Rector as types;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . DIRECTORY_SEPARATOR . 'php-src',
        __DIR__ . DIRECTORY_SEPARATOR . 'php-tests',
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets(php81: true)
    ->withTypeCoverageLevel(0)
    ->withRules([
        types\Property\AddPropertyTypeDeclarationRector::class,
        types\FunctionLike\AddReturnTypeDeclarationFromYieldsRector::class,
        types\FunctionLike\AddParamTypeSplFixedArrayRector::class,
        types\FunctionLike\AddParamTypeForFunctionLikeWithinCallLikeArgDeclarationRector::class,
        //        types\Closure\AddClosureVoidReturnTypeWhereNoReturnRector::class,
        types\Function_\AddFunctionVoidReturnTypeWhereNoReturnRector::class,
        types\ArrowFunction\AddArrowFunctionReturnTypeRector::class,
        types\ClassMethod\AddParamTypeFromPropertyTypeRector::class,
        dead_code\Property\RemoveUselessVarTagRector::class,
        dead_code\Property\RemoveUselessReadOnlyTagRector::class,
        dead_code\ClassMethod\RemoveUselessReturnTagRector::class,
        dead_code\ClassMethod\RemoveUselessParamTagRector::class,
        dead_code\ClassMethod\RemoveNullTagValueNodeRector::class,
        php_71\FuncCall\RemoveExtraParametersRector::class,
        coding_style\Stmt\RemoveUselessAliasInUseStatementRector::class,
        coding_style\ClassConst\RemoveFinalFromConstRector::class,
        Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector::class,
        types\Class_\ReturnTypeFromStrictTernaryRector::class,
        types\ClassMethod\ReturnTypeFromStrictNewArrayRector::class,
        types\ClassMethod\ReturnTypeFromStrictNativeCallRector::class,
        types\ClassMethod\NumericReturnTypeFromStrictReturnsRector::class,
        types\ClassMethod\NumericReturnTypeFromStrictScalarReturnsRector::class,
        types\ClassMethod\BoolReturnTypeFromBooleanStrictReturnsRector::class,
        types\ClassMethod\ReturnTypeFromStrictTypedPropertyRector::class,
        types\ClassMethod\ReturnTypeFromStrictFluentReturnRector::class,
        types\ClassMethod\ReturnTypeFromStrictConstantReturnRector::class,
        types\ClassMethod\ReturnTypeFromStrictTypedCallRector::class,
    ])
;
