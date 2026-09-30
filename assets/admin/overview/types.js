// @flow
export type Statistics = {|
    count: number,
    countPerStatus: {[status: string]: number},
    finalSize: number,
    originalSize: number,
    savedBytes: number,
    savedPercentage: number,
|};

export type Tool = {|
    available: boolean,
    formats: Array<string>,
    name: string,
    path: ?string,
|};

export type OverviewData = {|
    statistics: Statistics,
    tools: Array<Tool>,
|};
