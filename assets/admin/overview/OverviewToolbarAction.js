// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {Requester} from 'sulu-admin-bundle/services';
import {transformBytesToReadableString, translate} from 'sulu-admin-bundle/utils';
import {AbstractListToolbarAction} from 'sulu-admin-bundle/views';
import Overview from './Overview';
import type {OverviewData} from './types';

/**
 * A button above the log that shows how much was saved in total, and opens the totals together
 * with what the server has available to optimize with.
 */
export default class OverviewToolbarAction extends AbstractListToolbarAction {
    @observable data: ?OverviewData = undefined;
    @observable failed: boolean = false;
    @observable open: boolean = false;

    constructor(...args: Array<*>) {
        super(...args);

        this.load();
    }

    getNode() {
        return (
            <Overview
                data={this.data}
                failed={this.failed}
                key="eekes_image_optimizer.overview"
                onClose={this.handleClose}
                open={this.open}
            />
        );
    }

    getToolbarItemConfig() {
        const savedBytes = this.data ? this.data.statistics.savedBytes : null;

        return {
            icon: 'su-chart',
            label: savedBytes !== null && savedBytes > 0
                ? translate('eekes_image_optimizer.saved_in_total', {size: transformBytesToReadableString(savedBytes)})
                : translate('eekes_image_optimizer.overview'),
            onClick: this.handleClick,
            type: 'button',
        };
    }

    load() {
        const {statistics_url: url} = this.options;

        if (typeof url !== 'string') {
            throw new Error('The "statistics_url" option must be the URL of the statistics endpoint.');
        }

        Requester.get(url)
            .then(action((data: OverviewData) => {
                this.data = data;
                this.failed = false;
            }))
            .catch(action(() => {
                this.failed = true;
            }));
    }

    @action handleClick = () => {
        // The log may have changed since the page opened: reload the totals every time. The old
        // ones stay visible until the new ones arrive, so the button does not flicker.
        this.failed = false;
        this.load();
        this.open = true;
    };

    @action handleClose = () => {
        this.open = false;
    };
}
