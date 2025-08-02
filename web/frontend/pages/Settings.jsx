import React, { useState, useEffect, useCallback } from 'react';
import {
  Page,
  Layout,
  LegacyCard,
  FormLayout,
  TextField,
  Select,
  Checkbox,
  Button,
  Toast,
  Frame,
  LegacyStack,
  Text,
  Divider,
  Banner,
} from '@shopify/polaris';
import { TitleBar } from '@shopify/app-bridge-react';
import { useAuthenticatedFetch } from '../hooks';

export default function Settings() {
  const fetch = useAuthenticatedFetch();
  const [settings, setSettings] = useState({
    alert_email: '',
    alert_frequency: 'daily',
    notifications_enabled: true,
    daily_alert_time: '09:00:00',
    weekly_alert_day: 'monday',
    email_template_settings: {},
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testingEmail, setTestingEmail] = useState(false);
  const [toast, setToast] = useState(null);
  const [testEmail, setTestEmail] = useState('');

  const frequencyOptions = [
    { label: 'Instant', value: 'instant' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
  ];

  const dayOptions = [
    { label: 'Monday', value: 'monday' },
    { label: 'Tuesday', value: 'tuesday' },
    { label: 'Wednesday', value: 'wednesday' },
    { label: 'Thursday', value: 'thursday' },
    { label: 'Friday', value: 'friday' },
    { label: 'Saturday', value: 'saturday' },
    { label: 'Sunday', value: 'sunday' },
  ];

  const fetchSettings = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/low-stock-pulse/settings');
      const data = await response.json();
      
      if (data.success) {
        setSettings(data.data);
        setTestEmail(data.data.alert_email || '');
      } else {
        setToast({ content: data.message || 'Failed to fetch settings', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error fetching settings', error: true });
    } finally {
      setLoading(false);
    }
  }, [fetch]);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const handleSave = async () => {
    setSaving(true);
    try {
      const response = await fetch('/api/low-stock-pulse/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings),
      });

      const data = await response.json();
      
      if (data.success) {
        setToast({ content: 'Settings saved successfully' });
        setSettings(data.data);
      } else {
        setToast({ content: data.message || 'Failed to save settings', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error saving settings', error: true });
    } finally {
      setSaving(false);
    }
  };

  const handleTestEmail = async () => {
    if (!testEmail) {
      setToast({ content: 'Please enter an email address', error: true });
      return;
    }

    setTestingEmail(true);
    try {
      const response = await fetch('/api/low-stock-pulse/settings/test-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ test_email: testEmail }),
      });

      const data = await response.json();
      
      if (data.success) {
        setToast({ content: 'Test email sent successfully' });
      } else {
        setToast({ content: data.message || 'Failed to send test email', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error sending test email', error: true });
    } finally {
      setTestingEmail(false);
    }
  };

  const handleFieldChange = (field, value) => {
    setSettings(prev => ({
      ...prev,
      [field]: value,
    }));
  };

  const toastMarkup = toast ? (
    <Toast
      content={toast.content}
      error={toast.error}
      onDismiss={() => setToast(null)}
    />
  ) : null;

  return (
    <Frame>
      <Page
        title="Alert Settings"
        primaryAction={{
          content: 'Save Settings',
          onAction: handleSave,
          loading: saving,
        }}
      >
        <TitleBar title="Low Stock Pulse - Settings" />
        
        <Layout>
          <Layout.Section>
            <LegacyCard sectioned title="Email Configuration">
              <FormLayout>
                <TextField
                  label="Alert Email Address"
                  value={settings.alert_email || ''}
                  onChange={(value) => handleFieldChange('alert_email', value)}
                  placeholder="Leave empty to use shop owner email"
                  helpText="Email address where low stock alerts will be sent"
                />
                
                <Checkbox
                  label="Enable Notifications"
                  checked={settings.notifications_enabled}
                  onChange={(checked) => handleFieldChange('notifications_enabled', checked)}
                  helpText="Turn on/off all email notifications"
                />
              </FormLayout>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section>
            <LegacyCard sectioned title="Alert Frequency">
              <FormLayout>
                <Select
                  label="Alert Frequency"
                  options={frequencyOptions}
                  value={settings.alert_frequency}
                  onChange={(value) => handleFieldChange('alert_frequency', value)}
                  helpText="How often to send low stock alerts"
                />

                {settings.alert_frequency === 'daily' && (
                  <TextField
                    label="Daily Alert Time"
                    type="time"
                    value={settings.daily_alert_time}
                    onChange={(value) => handleFieldChange('daily_alert_time', value)}
                    helpText="Time of day to send daily alerts (24-hour format)"
                  />
                )}

                {settings.alert_frequency === 'weekly' && (
                  <>
                    <Select
                      label="Weekly Alert Day"
                      options={dayOptions}
                      value={settings.weekly_alert_day}
                      onChange={(value) => handleFieldChange('weekly_alert_day', value)}
                      helpText="Day of the week to send weekly alerts"
                    />
                    <TextField
                      label="Weekly Alert Time"
                      type="time"
                      value={settings.daily_alert_time}
                      onChange={(value) => handleFieldChange('daily_alert_time', value)}
                      helpText="Time of day to send weekly alerts (24-hour format)"
                    />
                  </>
                )}
              </FormLayout>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section>
            <LegacyCard sectioned title="Test Email">
              <LegacyStack vertical spacing="loose">
                <Text variant="bodyMd">
                  Send a test email to verify your email configuration is working correctly.
                </Text>
                
                <FormLayout>
                  <TextField
                    label="Test Email Address"
                    value={testEmail}
                    onChange={setTestEmail}
                    placeholder="Enter email address for testing"
                  />
                  
                  <Button
                    onClick={handleTestEmail}
                    loading={testingEmail}
                    disabled={!testEmail}
                  >
                    Send Test Email
                  </Button>
                </FormLayout>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>

          <Layout.Section>
            <LegacyCard sectioned title="Alert Information">
              <LegacyStack vertical spacing="loose">
                <Banner status="info">
                  <LegacyStack vertical spacing="tight">
                    <Text variant="bodyMd" fontWeight="semibold">How alerts work:</Text>
                    <Text variant="bodyMd">
                      • <strong>Instant:</strong> Alerts are sent immediately when inventory falls below threshold
                    </Text>
                    <Text variant="bodyMd">
                      • <strong>Daily:</strong> A summary email is sent once per day with all products below threshold
                    </Text>
                    <Text variant="bodyMd">
                      • <strong>Weekly:</strong> A summary email is sent once per week with all products below threshold
                    </Text>
                  </LegacyStack>
                </Banner>

                <Divider />

                <LegacyStack vertical spacing="tight">
                  <Text variant="bodyMd" fontWeight="semibold">Email Template:</Text>
                  <Text variant="bodyMd">
                    Email templates can be customized from your Shopify admin under Settings → Notifications.
                    Look for "Low Stock Pulse" email templates.
                  </Text>
                </LegacyStack>
              </LegacyStack>
            </LegacyCard>
          </Layout.Section>
        </Layout>
        
        {toastMarkup}
      </Page>
    </Frame>
  );
}
