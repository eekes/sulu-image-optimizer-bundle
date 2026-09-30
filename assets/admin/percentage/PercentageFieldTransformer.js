// @flow
import type {Node} from 'react';
import {userStore} from 'sulu-admin-bundle/stores';
import type {FieldTransformer} from 'sulu-admin-bundle/containers/List/types';

export default class PercentageFieldTransformer implements FieldTransformer {
    transform(value: *): Node {
        if (value === undefined || value === null || value === '' || isNaN(value)) {
            return null;
        }

        return new Intl.NumberFormat(userStore.systemLocale, {maximumFractionDigits: 1}).format(Number(value)) + ' %';
    }
}
