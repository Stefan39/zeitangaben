<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben;

enum ClarificationReason: string
{
    case MissingState = 'missing_state';
    case MissingReferenceDate = 'missing_reference_date';
    case Ambiguous = 'ambiguous';
    case Vague = 'vague';
    case NotACalendarTerm = 'not_a_calendar_term';
    case OutOfData = 'out_of_data';
    case NotInState = 'not_in_state';
    case InvalidDate = 'invalid_date';
    case MultipleExpressions = 'multiple_expressions';
    case UnclearContext = 'unclear_context';
}
