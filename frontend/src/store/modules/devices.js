import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useDevicesStore = defineStore("devices", {
     state: () => ({
          devices: [],
          options: { profiles: [], labs: [], can_select_lab: false },
          deviceResults: [],
          resultsPagination: { current_page: 1, total: 0, per_page: 25, last_page: 1 },
          resultsParams: {},
          resultsLoading: false,
          resultsError: false,
          resultsRequestId: 0,
          totalCount: 0,
          pendingCount: 0,
          dialog: false,
          resultDialog: false,
          newTokenDialog: false,
          newToken: "",
          record: {
               id: "",
               lab_id_fk: "",
               name: "",
               device_type: "",
               serial_number: "",
               connection_type: "serial",
               connection_config: { com_port: "", baud_rate: 9600, bridge_adapter: "dxh500", cbc_interface_code: "12345678", automatic_invoice_apply: true },
          },
          selectedResult: null,
          pollingInterval: null,
     }),
     actions: {
          async GetDeviceOptions() {
               const { data } = await $http.get("/devices/options");
               this.options = data;
          },
          async GetDevices() {
               try {
                    const { data } = await $http.get("/devices");
                    this.devices = Array.isArray(data) ? data.map((item, i) => ({ ...item, index: i + 1 })) : [];
                    this.totalCount = this.devices.length;
                    this.pendingCount = this.devices.reduce((sum, d) => sum + (d.pending_results_count || 0), 0);
               } catch (error) {
                    console.error("Error fetching devices:", error);
                    throw error;
               }
          },
          async AddDevice() {
               try {
                    const { data } = await $http.post("/devices/create", checkObjectParams(this.record));
                    this.newToken = data.api_token;
                    this.newTokenDialog = true;
                    await this.GetDevices();
                    return data;
               } catch (error) {
                    console.error("Error adding device:", error);
                    throw error;
               }
          },
          async UpdateDevice() {
               try {
                    const { data } = await $http.put("/devices/update", checkObjectParams(this.record));
                    await this.GetDevices();
                    return data;
               } catch (error) {
                    console.error("Error updating device:", error);
                    throw error;
               }
          },
          async RemoveDevice() {
               try {
                    await $http.delete("/devices/delete", { data: { id: this.record.id } });
                    await this.GetDevices();
               } catch (error) {
                    console.error("Error removing device:", error);
                    throw error;
               }
          },
          async RegenerateToken(deviceId) {
               try {
                    const { data } = await $http.post("/devices/regenerate-token", { id: deviceId });
                    this.newToken = data.api_token;
                    this.newTokenDialog = true;
                    return data;
               } catch (error) {
                    console.error("Error regenerating token:", error);
                    throw error;
               }
          },
          async GetDeviceResults(params = this.resultsParams) {
               this.resultsParams = { ...params };
               const requestId = ++this.resultsRequestId;
               this.resultsLoading = true;
               try {
                    const { data } = await $http.get("/device-results", { params: { ...params } });
                    if (requestId !== this.resultsRequestId) return;
                    this.deviceResults = data.data || [];
                    this.resultsPagination = data.pagination || {};
                    this.resultsError = false;
               } catch (error) {
                    if (requestId !== this.resultsRequestId) return;
                    // Keep the last successful snapshot and make the refresh failure visible.
                    this.resultsError = true;
               } finally {
                    if (requestId === this.resultsRequestId) this.resultsLoading = false;
               }
          },
          async ApplyResult(resultId, replaceExisting = false) {
               try {
                    const { data } = await $http.post(`/device-results/${resultId}/apply`, { replace_existing: replaceExisting });
                    await Promise.allSettled([this.GetDeviceResults(), this.GetDevices()]);
                    return data;
               } catch (error) {
                    console.error("Error applying result:", error);
                    throw error;
               }
          },
          async ManualMatch(resultId, invoiceId) {
               try {
                    const { data } = await $http.post(`/device-results/${resultId}/match`, { invoice_id: invoiceId });
                    await this.GetDeviceResults();
                    return data;
               } catch (error) {
                    console.error("Error matching result:", error);
                    throw error;
               }
          },
          startPolling(intervalMs = 30000, includeResults = () => true) {
               this.stopPolling();
               this.pollingInterval = setInterval(() => {
                    if (document.hidden || this.resultsLoading) return;
                    void Promise.allSettled([this.GetDevices(), ...(includeResults() ? [this.GetDeviceResults()] : [])]);
               }, intervalMs);
          },
          stopPolling() {
               if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = null;
               }
          },
     },
});
