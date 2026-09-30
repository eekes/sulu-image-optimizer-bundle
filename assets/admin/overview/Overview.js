// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Dialog, Loader} from 'sulu-admin-bundle/components';
import {transformBytesToReadableString, translate} from 'sulu-admin-bundle/utils';
import StatusBadge from '../status/StatusBadge';
import type {OverviewData} from './types';
import overviewStyles from './overview.scss';

type Props = {|
    data: ?OverviewData,
    failed: boolean,
    onClose: () => void,
    open: boolean,
|};

const STATUSES = ['optimized', 'unchanged', 'skipped', 'failed'];

function bytes(value: number): string {
    return value > 0 ? transformBytesToReadableString(value) : '0 Bytes';
}

@observer
class Overview extends React.Component<Props> {
    render() {
        const {onClose, open} = this.props;

        return (
            <Dialog
                confirmText={translate('eekes_image_optimizer.close')}
                onConfirm={onClose}
                open={open}
                size="large"
                title={translate('eekes_image_optimizer.overview_title')}
            >
                {this.renderContent()}
            </Dialog>
        );
    }

    renderContent() {
        const {data, failed} = this.props;

        if (failed) {
            return <p>{translate('eekes_image_optimizer.loading_failed')}</p>;
        }

        if (!data) {
            return <Loader />;
        }

        const {statistics, tools} = data;

        return (
            <div className={overviewStyles.overview}>
                <h3>{translate('eekes_image_optimizer.totals')}</h3>
                <table className={overviewStyles.table}>
                    <tbody>
                        <tr>
                            <th>{translate('eekes_image_optimizer.entries')}</th>
                            <td>{statistics.count}</td>
                        </tr>
                        <tr>
                            <th>{translate('eekes_image_optimizer.size_before')}</th>
                            <td>{bytes(statistics.originalSize)}</td>
                        </tr>
                        <tr>
                            <th>{translate('eekes_image_optimizer.size_after')}</th>
                            <td>{bytes(statistics.finalSize)}</td>
                        </tr>
                        <tr>
                            <th>{translate('eekes_image_optimizer.saved_total')}</th>
                            <td>{bytes(statistics.savedBytes)} ({statistics.savedPercentage} %)</td>
                        </tr>
                        {STATUSES.map((status) => (
                            <tr key={status}>
                                <th>
                                    <StatusBadge
                                        label={translate('eekes_image_optimizer.status_' + status)}
                                        status={status}
                                    />
                                </th>
                                <td>{statistics.countPerStatus[status] || 0}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <h3>{translate('eekes_image_optimizer.server')}</h3>
                <p className={overviewStyles.info}>{translate('eekes_image_optimizer.server_info')}</p>
                <table className={overviewStyles.table}>
                    <thead>
                        <tr>
                            <th>{translate('eekes_image_optimizer.tool')}</th>
                            <th>{translate('eekes_image_optimizer.formats')}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {tools.map((tool) => (
                            <tr key={tool.name}>
                                <td title={tool.path || undefined}>{tool.name}</td>
                                <td>{tool.formats.join(', ')}</td>
                                <td>
                                    <StatusBadge
                                        label={translate(
                                            tool.available ? 'eekes_image_optimizer.available' : 'eekes_image_optimizer.missing'
                                        )}
                                        status={tool.available ? 'unchanged' : 'failed'}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }
}

export default Overview;
