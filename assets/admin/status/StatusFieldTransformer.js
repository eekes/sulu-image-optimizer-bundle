// @flow
import React from 'react';
import type {Node} from 'react';
import {translate} from 'sulu-admin-bundle/utils';
import type {FieldTransformer} from 'sulu-admin-bundle/containers/List/types';
import StatusBadge from './StatusBadge';

export default class StatusFieldTransformer implements FieldTransformer {
    transform(value: *): Node {
        if (typeof value !== 'string' || value === '') {
            return null;
        }

        return <StatusBadge label={translate('eekes_image_optimizer.status_' + value)} status={value} />;
    }
}
