import React, { useState, useEffect, useCallback } from 'react';
import {
  Page,
  Layout,
  LegacyCard,
  LegacyStack,
  Text,
  Badge,
  Button,
  Spinner,
  Toast,
  Frame,
  Link,
} from '@shopify/polaris';
import { TitleBar } from '@shopify/app-bridge-react';
import { useAuthenticatedFetch } from '../hooks';
import { useNavigate } from 'react-router-dom';

export default function Dashboard() {
  const fetch = useAuthenticatedFetch();
  const navigate = useNavigate();
  const [stats, setStats] = useState({
    total_products_tracked: 0,
    active_alerts: 0,
    products_below_threshold: 0,
    alerts_sent_last_7_days: 0,
    last_email_sent_at: null,
    notifications_enabled: true,
    alert_frequency: 'daily',
  });
  const [recentLogs, setRecentLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState(null);

  const fetchDashboardData = useCallback(async () => {
    setLoading(true);
    try {
      // Fetch dashboard stats
      const statsResponse = await fetch('/api/low-stock-pulse/dashboard/stats');

      if (!statsResponse.ok) {
        throw new Error(`Stats API failed with status: ${statsResponse.status}`);
      }

      const statsData = await statsResponse.json();

      if (statsData.success) {
        setStats(statsData.data);
      } else {
        setToast({ content: statsData.message || 'Failed to fetch stats', error: true });
      }

      // Fetch recent activity logs
      const logsResponse = await fetch('/api/low-stock-pulse/settings/recent-logs');

      if (!logsResponse.ok) {
        throw new Error(`Logs API failed with status: ${logsResponse.status}`);
      }

      const logsData = await logsResponse.json();

      if (logsData.success) {
        setRecentLogs(logsData.data);
      }

    } catch (error) {
      console.error('Dashboard fetch error:', error);
      setToast({ content: 'Error fetching dashboard data: ' + error.message, error: true });
    } finally {
      setLoading(false);
    }
  }, []); // Remove fetch dependency to prevent infinite loop

  useEffect(() => {
    fetchDashboardData();
  }, []); // Empty dependency array - only run once on mount

  const formatDate = (dateString) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
  };

  const getStatusColor = (count) => {
    if (count === 0) return 'success';
    if (count <= 5) return 'warning';
    return 'critical';
  };

  const toastMarkup = toast ? (
    <Toast
      content={toast.content}
      error={toast.error}
      onDismiss={() => setToast(null)}
    />
  ) : null;

  if (loading) {
    return (
      <Frame>
        <Page title="Dashboard">
          <div style={{ textAlign: 'center', padding: '2rem' }}>
            <Spinner size="large" />
          </div>
        </Page>
      </Frame>
    );
  }

  return (
    <Frame>
      <Page
        title="Low Stock Pulse Dashboard"
        primaryAction={{
          content: 'Manage Products',
          onAction: () => navigate('/products'),
        }}
        secondaryActions={[
          {
            content: 'Settings',
            onAction: () => navigate('/settings'),
          },
        ]}
      >
        <TitleBar title="Low Stock Pulse - Dashboard" />
        
        <Layout>
          {/* Status Cards */}
          <Layout.Section oneHalf>
            <LegacyCard sectioned>
              <LegacyStack vertical spacing="tight">
                <Text variant="headingMd">Products Tracked</Text>
                <Text variant="heading2xl">{stats.total_products_tracked}</Text>
                <Text variant="bodyMd" color="subdued">
                  Total products with thresholds set
                </Text>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section oneHalf>
            <LegacyCard sectioned>
              <LegacyStack vertical spacing="tight">
                <Text variant="headingMd">Active Alerts</Text>
                <Text variant="heading2xl">{stats.active_alerts}</Text>
                <Text variant="bodyMd" color="subdued">
                  Products with alerts enabled
                </Text>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section oneHalf>
            <LegacyCard sectioned>
              <LegacyStack vertical spacing="tight">
                <LegacyStack distribution="equalSpacing" alignment="center">
                  <Text variant="headingMd">Below Threshold</Text>
                  <Badge status={getStatusColor(stats.products_below_threshold)}>
                    {stats.products_below_threshold === 0 ? 'All Good' : 'Attention Needed'}
                  </Badge>
                </LegacyStack>
                <Text variant="heading2xl">{stats.products_below_threshold}</Text>
                <Text variant="bodyMd" color="subdued">
                  Products currently below their threshold
                </Text>
                {stats.products_below_threshold > 0 && (
                  <Button
                    size="slim"
                    onClick={() => navigate('/products')}
                  >
                    View Products
                  </Button>
                )}
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section oneHalf>
            <LegacyCard sectioned>
              <LegacyStack vertical spacing="tight">
                <Text variant="headingMd">Recent Alerts</Text>
                <Text variant="heading2xl">{stats.alerts_sent_last_7_days}</Text>
                <Text variant="bodyMd" color="subdued">
                  Emails sent in the last 7 days
                </Text>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          {/* Settings Overview */}
          <Layout.Section>
            <LegacyCard sectioned title="Current Settings">
              <LegacyStack distribution="equalSpacing" alignment="center">
                <LegacyStack vertical spacing="tight">
                  <Text variant="bodyMd" fontWeight="semibold">Notifications</Text>
                  <Badge status={stats.notifications_enabled ? 'success' : 'critical'}>
                    {stats.notifications_enabled ? 'Enabled' : 'Disabled'}
                  </Badge>
                </LegacyStack>

                <LegacyStack vertical spacing="tight">
                  <Text variant="bodyMd" fontWeight="semibold">Alert Frequency</Text>
                  <Badge status="info">
                    {stats.alert_frequency.charAt(0).toUpperCase() + stats.alert_frequency.slice(1)}
                  </Badge>
                </LegacyStack>

                <LegacyStack vertical spacing="tight">
                  <Text variant="bodyMd" fontWeight="semibold">Last Email Sent</Text>
                  <Text variant="bodyMd" color="subdued">
                    {formatDate(stats.last_email_sent_at)}
                  </Text>
                </LegacyStack>

                <Button
                  onClick={() => navigate('/settings')}
                >
                  Update Settings
                </Button>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          {/* Recent Activity */}
          <Layout.Section>
            <LegacyCard sectioned title="Recent Activity">
              {recentLogs.length === 0 ? (
                <LegacyStack vertical spacing="loose">
                  <Text variant="bodyMd" color="subdued">
                    No recent email activity. Set up product thresholds to start receiving alerts.
                  </Text>
                  <div style={{ marginTop: '1rem' }}>
                    <Button onClick={() => navigate('/products')}>
                      Set Up Products
                    </Button>
                  </div>
                </LegacyStack>
              ) : (
                <LegacyStack vertical spacing="loose">
                  {recentLogs.slice(0, 5).map((log, index) => (
                    <LegacyStack key={index} distribution="equalSpacing" alignment="center">
                      <LegacyStack vertical spacing="extraTight">
                        <Text variant="bodyMd" fontWeight="semibold">
                          {log.display_name || `${log.product_title}${log.variant_title ? ` - ${log.variant_title}` : ''}`}
                        </Text>
                        <Text variant="bodyMd" color="subdued">
                          Stock: {log.current_quantity} (Threshold: {log.threshold_quantity})
                        </Text>
                      </LegacyStack>

                      <LegacyStack vertical spacing="extraTight" alignment="trailing">
                        <Badge status={log.email_sent_successfully ? 'success' : 'critical'}>
                          {log.email_sent_successfully ? 'Sent' : 'Failed'}
                        </Badge>
                        <Text variant="bodyMd" color="subdued">
                          {formatDate(log.created_at)}
                        </Text>
                      </LegacyStack>
                    </LegacyStack>
                  ))}

                  <div style={{ textAlign: 'center', marginTop: '1rem' }}>
                    <Link onClick={() => navigate('/activity-log')}>
                      View All Activity
                    </Link>
                  </div>
                </LegacyStack>
              )}
            </LegacyCard>
          </Layout.Section>
        </Layout>
        
        {toastMarkup}
      </Page>
    </Frame>
  );
}
