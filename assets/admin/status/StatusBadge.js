// @flow
import React from 'react';
import classNames from 'classnames';
import statusBadgeStyles from './statusBadge.scss';

type Props = {|
    label: string,
    status: string,
|};

// Sulu's own Badge has a single colour; the status needs one per value to be readable at a glance.
export default function StatusBadge({label, status}: Props) {
    return (
        <span className={classNames(statusBadgeStyles.badge, statusBadgeStyles[status])}>
            {label}
        </span>
    );
}
