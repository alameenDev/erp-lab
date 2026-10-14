export const reportTests = report => Array.isArray(report?.tests) ? report.tests : [];
export function portalReportCounts(reports) {
  const tests = reports.flatMap(reportTests);
  const ready = tests.filter(test => test.status === 'ready').length;
  return { all: tests.length, ready, pending: tests.length - ready };
}
export function filterPortalReports(reports, filter) {
  return reports.map(report => ({ ...report,
    visibleTests: reportTests(report).filter(test => filter === 'all' || test.status === filter),
    counts: portalReportCounts([report]),
  })).filter(report => filter === 'all' || report.visibleTests.length > 0);
}
