import React, { useState, useEffect, useCallback } from 'react';
import {
  Page,
  Layout,
  LegacyCard,
  DataTable,
  Badge,
  Spinner,
  Toast,
  Frame,
  LegacyStack,
  Text,
  Pagination,
  EmptyState,
  Filters,
  ChoiceList,
} from '@shopify/polaris';
import { TitleBar } from '@shopify/app-bridge-react';
import { useAuthenticatedFetch } from '../hooks';

export default function ActivityLog() {
  const fetch = useAuthenticatedFetch();
  const [activityLogs, setActivityLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState(null);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    hasMorePages: false,
    total: 0,
  });
  const [filters, setFilters] = useState({
    status: [],
    alertType: [],
  });

  const fetchActivityLogs = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const response = await fetch(`/api/low-stock-pulse/settings/activity-logs?page=${page}&limit=10`);
      const data = await response.json();
      
      if (data.success) {
        setActivityLogs(data.data);
        setPagination(data.pagination);
      } else {
        setToast({ content: data.message || 'Failed to fetch activity logs', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error fetching activity logs', error: true });
    } finally {
      setLoading(false);
    }
  }, []); // Remove fetch dependency to prevent infinite loop

  useEffect(() => {
    fetchActivityLogs();
  }, []); // Empty dependency array - only run once on mount

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString();
  };

  const getStatusBadge = (emailSent, errorMessage) => {
    if (emailSent) {
      return <Badge status="success">Sent</Badge>;
    } else {
      return <Badge status="critical">Failed</Badge>;
    }
  };

  const getAlertTypeBadge = (alertType) => {
    const typeMap = {
      instant: { status: 'info', label: 'Instant' },
      daily_batch: { status: 'attention', label: 'Daily Batch' },
      weekly_batch: { status: 'warning', label: 'Weekly Batch' },
    };
    
    const config = typeMap[alertType] || { status: 'info', label: alertType };
    return <Badge status={config.status}>{config.label}</Badge>;
  };

  // Filter the activity logs based on selected filters
  const filteredLogs = activityLogs.filter(log => {
    const statusMatch = filters.status.length === 0 || 
      filters.status.includes(log.email_sent_successfully ? 'sent' : 'failed');
    
    const typeMatch = filters.alertType.length === 0 || 
      filters.alertType.includes(log.alert_type);
    
    return statusMatch && typeMatch;
  });

  // Prepare data for DataTable
  const tableRows = filteredLogs.map(log => [
    formatDate(log.created_at),
    log.display_name || `${log.product_title}${log.variant_title ? ` - ${log.variant_title}` : ''}`,
    log.current_quantity,
    log.threshold_quantity,
    log.alert_email,
    getAlertTypeBadge(log.alert_type),
    getStatusBadge(log.email_sent_successfully, log.email_error_message),
    log.email_error_message || '-',
  ]);

  const handleFiltersChange = useCallback((newFilters) => {
    setFilters(newFilters);
  }, []);

  const handleFiltersClear = useCallback(() => {
    setFilters({
      status: [],
      alertType: [],
    });
  }, []);

  const filterOptions = [
    {
      key: 'status',
      label: 'Status',
      filter: (
        <ChoiceList
          title="Status"
          titleHidden
          choices={[
            { label: 'Sent', value: 'sent' },
            { label: 'Failed', value: 'failed' },
          ]}
          selected={filters.status}
          onChange={(value) => handleFiltersChange({ ...filters, status: value })}
          allowMultiple
        />
      ),
      shortcut: true,
    },
    {
      key: 'alertType',
      label: 'Alert Type',
      filter: (
        <ChoiceList
          title="Alert Type"
          titleHidden
          choices={[
            { label: 'Instant', value: 'instant' },
            { label: 'Daily Batch', value: 'daily_batch' },
            { label: 'Weekly Batch', value: 'weekly_batch' },
          ]}
          selected={filters.alertType}
          onChange={(value) => handleFiltersChange({ ...filters, alertType: value })}
          allowMultiple
        />
      ),
      shortcut: true,
    },
  ];

  const appliedFilters = [];
  if (filters.status.length > 0) {
    appliedFilters.push({
      key: 'status',
      label: `Status: ${filters.status.join(', ')}`,
      onRemove: () => handleFiltersChange({ ...filters, status: [] }),
    });
  }
  if (filters.alertType.length > 0) {
    appliedFilters.push({
      key: 'alertType',
      label: `Alert Type: ${filters.alertType.join(', ')}`,
      onRemove: () => handleFiltersChange({ ...filters, alertType: [] }),
    });
  }

  const toastMarkup = toast ? (
    <Toast
      content={toast.content}
      error={toast.error}
      onDismiss={() => setToast(null)}
    />
  ) : null;

  const emptyStateMarkup = !loading && filteredLogs.length === 0 ? (
    <EmptyState
      heading="No activity logs found"
      image="https://cdn.shopify.com/s/files/1/0262/4071/2726/files/emptystate-files.png"
    >
      <p>
        {activityLogs.length === 0 
          ? "No email alerts have been sent yet. Set up product thresholds to start receiving alerts."
          : "No logs match your current filters. Try adjusting your filter criteria."
        }
      </p>
    </EmptyState>
  ) : null;

  return (
    <Frame>
      <Page title="Activity Log">
        <TitleBar title="Low Stock Pulse - Activity Log" />
        
        <Layout>
          <Layout.Section>
            <LegacyCard>
              <div style={{ padding: '1rem' }}>
                <LegacyStack vertical spacing="loose">
                  <Text variant="headingMd">Email Alert History</Text>
                  <Text variant="bodyMd" color="subdued">
                    View the last email alerts sent for low stock products.
                    This log shows the most recent activity across all your products.
                  </Text>
                </LegacyStack>
              </div>
              
              <Filters
                queryValue=""
                filters={filterOptions}
                appliedFilters={appliedFilters}
                onQueryClear={() => {}}
                onClearAll={handleFiltersClear}
              />

              {loading ? (
                <div style={{ textAlign: 'center', padding: '2rem' }}>
                  <Spinner size="large" />
                </div>
              ) : emptyStateMarkup ? (
                emptyStateMarkup
              ) : (
                <>
                  <DataTable
                    columnContentTypes={[
                      'text',
                      'text',
                      'numeric',
                      'numeric',
                      'text',
                      'text',
                      'text',
                      'text',
                    ]}
                    headings={[
                      'Date & Time',
                      'Product',
                      'Current Stock',
                      'Threshold',
                      'Email Sent To',
                      'Alert Type',
                      'Status',
                      'Error Message',
                    ]}
                    rows={tableRows}
                  />
                  
                  {pagination.hasMorePages && (
                    <div style={{ padding: '1rem', textAlign: 'center' }}>
                      <Pagination
                        hasPrevious={pagination.currentPage > 1}
                        onPrevious={() => fetchActivityLogs(pagination.currentPage - 1)}
                        hasNext={pagination.hasMorePages}
                        onNext={() => fetchActivityLogs(pagination.currentPage + 1)}
                      />
                    </div>
                  )}
                  
                  <div style={{ padding: '1rem', textAlign: 'center' }}>
                    <Text variant="bodyMd" color="subdued">
                      Showing {filteredLogs.length} of {pagination.total} total logs
                    </Text>
                  </div>
                </>
              )}
            </LegacyCard>
          </Layout.Section>
        </Layout>
        
        {toastMarkup}
      </Page>
    </Frame>
  );
}
