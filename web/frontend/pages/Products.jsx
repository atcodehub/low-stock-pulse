import React, { useState, useEffect, useCallback } from 'react';
import {
  Page,
  Layout,
  LegacyCard,
  DataTable,
  Button,
  TextField,
  Checkbox,
  Badge,
  Spinner,
  Toast,
  Frame,
  Modal,
  FormLayout,
  LegacyStack,
  Text,
  Pagination,
} from '@shopify/polaris';
import { TitleBar } from '@shopify/app-bridge-react';
import { useAuthenticatedFetch } from '../hooks';

export default function Products() {
  const fetch = useAuthenticatedFetch();
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);
  const [toast, setToast] = useState(null);
  const [modalActive, setModalActive] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState(null);
  const [thresholdValue, setThresholdValue] = useState('');
  const [pagination, setPagination] = useState({
    hasNextPage: false,
    endCursor: null,
  });

  const fetchProducts = useCallback(async (cursor = null) => {
    setLoading(true);
    try {
      const url = cursor 
        ? `/api/low-stock-pulse/products?cursor=${cursor}`
        : '/api/low-stock-pulse/products';
      
      const response = await fetch(url);
      const data = await response.json();
      
      if (data.success) {
        setProducts(data.data.products);
        setPagination(data.data.pagination);
      } else {
        setToast({ content: data.message || 'Failed to fetch products', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error fetching products', error: true });
    } finally {
      setLoading(false);
    }
  }, []); // Remove fetch dependency to prevent infinite loop

  useEffect(() => {
    fetchProducts();
  }, []); // Empty dependency array - only run once on mount

  const handleThresholdUpdate = async (productId, variantId, newThreshold, productTitle, variantTitle) => {
    setUpdating(true);
    try {
      const response = await fetch('/api/low-stock-pulse/products/threshold', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_id: productId,
          variant_id: variantId,
          threshold_quantity: parseInt(newThreshold),
          product_title: productTitle,
          variant_title: variantTitle,
        }),
      });

      const data = await response.json();
      
      if (data.success) {
        setToast({ content: 'Threshold updated successfully' });
        fetchProducts(); // Refresh the data
      } else {
        setToast({ content: data.message || 'Failed to update threshold', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error updating threshold', error: true });
    } finally {
      setUpdating(false);
      setModalActive(false);
    }
  };

  const handleAlertToggle = async (productId, variantId, enabled) => {
    try {
      const response = await fetch('/api/low-stock-pulse/products/toggle-alerts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_id: productId,
          variant_id: variantId,
          alerts_enabled: enabled,
        }),
      });

      const data = await response.json();

      if (data.success) {
        setToast({ content: `Alerts ${enabled ? 'enabled' : 'disabled'} successfully` });
        fetchProducts(); // Refresh the data
      } else {
        setToast({ content: data.message || 'Failed to toggle alerts', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error toggling alerts', error: true });
    }
  };

  const refreshInventory = async () => {
    setUpdating(true);
    try {
      const response = await fetch('/api/low-stock-pulse/products/update-inventory', {
        method: 'POST',
      });

      const data = await response.json();

      if (data.success) {
        setToast({ content: `Inventory updated for ${data.updated_count} products` });
        fetchProducts(); // Refresh the data to show updated inventory
      } else {
        setToast({ content: data.message || 'Failed to update inventory', error: true });
      }
    } catch (error) {
      setToast({ content: 'Error updating inventory', error: true });
    } finally {
      setUpdating(false);
    }
  };

  const openThresholdModal = (product, variant) => {
    setSelectedProduct({ product, variant });
    setThresholdValue(variant.threshold_quantity.toString());
    setModalActive(true);
  };



  // Prepare data for DataTable
  const tableRows = [];
  products.forEach(product => {
    product.variants.forEach(variant => {
      const statusBadge = variant.is_below_threshold ? (
        <Badge status="critical">Below Threshold</Badge>
      ) : (
        <Badge status="success">OK</Badge>
      );

      tableRows.push([
        product.title,
        variant.title !== 'Default Title' ? variant.title : '-',
        variant.sku || '-',
        variant.inventory_quantity,
        variant.threshold_quantity,
        statusBadge,
        <Checkbox
          checked={variant.alerts_enabled}
          onChange={(checked) => handleAlertToggle(product.id, variant.id, checked)}
        />,
        <Button
          size="slim"
          onClick={() => openThresholdModal(product, variant)}
        >
          Edit Threshold
        </Button>,
      ]);
    });
  });

  const toastMarkup = toast ? (
    <Toast
      content={toast.content}
      error={toast.error}
      onDismiss={() => setToast(null)}
    />
  ) : null;

  const modalMarkup = (
    <Modal
      open={modalActive}
      onClose={() => setModalActive(false)}
      title="Set Threshold"
      primaryAction={{
        content: 'Update',
        onAction: () => {
          if (selectedProduct) {
            handleThresholdUpdate(
              selectedProduct.product.id,
              selectedProduct.variant.id,
              thresholdValue,
              selectedProduct.product.title,
              selectedProduct.variant.title
            );
          }
        },
        loading: updating,
      }}
      secondaryActions={[
        {
          content: 'Cancel',
          onAction: () => setModalActive(false),
        },
      ]}
    >
      <Modal.Section>
        <FormLayout>
          <TextField
            label="Threshold Quantity"
            type="number"
            value={thresholdValue}
            onChange={setThresholdValue}
            helpText="Alert will be sent when inventory falls to or below this number"
          />
          {selectedProduct && (
            <LegacyStack vertical spacing="tight">
              <Text variant="bodyMd" fontWeight="semibold">Product Details:</Text>
              <Text variant="bodyMd">Product: {selectedProduct.product.title}</Text>
              <Text variant="bodyMd">Variant: {selectedProduct.variant.title}</Text>
              <Text variant="bodyMd">Current Inventory: {selectedProduct.variant.inventory_quantity}</Text>
            </LegacyStack>
          )}
        </FormLayout>
      </Modal.Section>
    </Modal>
  );

  return (
    <Frame>
      <Page
        title="Product Inventory Management"
        primaryAction={{
          content: 'Refresh Inventory from Shopify',
          onAction: refreshInventory,
          loading: updating,
        }}
      >
        <TitleBar title="Low Stock Pulse - Products" />
        
        <Layout>
          <Layout.Section>
            <LegacyCard>
              {loading ? (
                <div style={{ textAlign: 'center', padding: '2rem' }}>
                  <Spinner size="large" />
                </div>
              ) : (
                <>
                  <DataTable
                    columnContentTypes={[
                      'text',
                      'text',
                      'text',
                      'numeric',
                      'numeric',
                      'text',
                      'text',
                      'text',
                    ]}
                    headings={[
                      'Product',
                      'Variant',
                      'SKU',
                      'Current Stock',
                      'Threshold',
                      'Status',
                      'Alerts',
                      'Actions',
                    ]}
                    rows={tableRows}
                  />
                  
                  {(pagination.hasNextPage) && (
                    <div style={{ padding: '1rem', textAlign: 'center' }}>
                      <Pagination
                        hasNext={pagination.hasNextPage}
                        onNext={() => fetchProducts(pagination.endCursor)}
                      />
                    </div>
                  )}
                </>
              )}
            </LegacyCard>
          </Layout.Section>
        </Layout>
        
        {modalMarkup}
        {toastMarkup}
      </Page>
    </Frame>
  );
}
