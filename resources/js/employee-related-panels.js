import { relatedPanels } from './workspace-related-panels';

// Employee keeps its own data attributes; the behaviour is the shared workspace kit.
export function employeeRelatedPanels() {
    return relatedPanels({
        hostSelector: '[data-employee-related-host]',
        linkSelector: '[data-employee-related]',
        key: 'employeeRelated',
    });
}
