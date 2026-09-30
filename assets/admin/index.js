// @flow
import {listFieldTransformerRegistry} from 'sulu-admin-bundle/containers';
import {listToolbarActionRegistry} from 'sulu-admin-bundle/views';
import StatusFieldTransformer from './status/StatusFieldTransformer';
import PercentageFieldTransformer from './percentage/PercentageFieldTransformer';
import OverviewToolbarAction from './overview/OverviewToolbarAction';

// The keys are the ones config/lists/image_optimizations.xml and ImageOptimizationAdmin refer to.
listFieldTransformerRegistry.add('eekes_image_optimizer_status', new StatusFieldTransformer());
listFieldTransformerRegistry.add('eekes_image_optimizer_percentage', new PercentageFieldTransformer());
listToolbarActionRegistry.add('eekes_image_optimizer.overview', OverviewToolbarAction);

export {
    OverviewToolbarAction,
    PercentageFieldTransformer,
    StatusFieldTransformer,
};
