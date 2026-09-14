import { defineStore } from "pinia";
import { $http } from "@/plugins/axios";
import { checkObjectParams } from "@/utils/helper";

export const useDevicesStore = defineStore("devices", {
     state: () => ({
          devices: [],
          deviceResults: [],
          resultsPagination: { current_page: 1, total: 0, per_page: 25, last_page: 1 },
          totalCount: 0,
          pendingCount: 0,
          dialog: false,
          resultDialog: false,
          newTokenDialog: false,
          newToken: "",
          record: {
               id: "",
               name: "",
               device_type: "",
               serial_number: "",
               connection_type: "serial",
               connection_config: { com_port: "", baud_rate: 9600 },
          },
          selectedResult: null,
          pollingInterval: null,
     }),
     actions: {
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
          async GetDeviceResults(params = {}) {
               try {
                    const { data } = await $http.get("/device-results", { params });
                    this.deviceResults = data.data || [];
                    this.resultsPagination = data.pagination || {};
               } catch (error) {
                    console.error("Error fetching device results:", error);
                    throw error;
               }
          },
          async ApplyResult(resultId) {
               try {
                    const { data } = await $http.post(`/device-results/${resultId}/apply`);
                    await this.GetDeviceResults({ status: "matched" });
                    await this.GetDevices();
                    return data;
               } catch (error) {
                    console.error("Error applying result:", error);
                    throw error;
               }
          },
          async ManualMatch(resultId, invoiceId) {
               try {
                    const { data } = await $http.post(`/device-results/${resultId}/match`, { invoice_id: invoiceId });
                    await this.GetDeviceResults({ status: "pending" });
                    return data;
               } catch (error) {
                    console.error("Error matching result:", error);
                    throw error;
               }
          },
          startPolling(intervalMs = 30000) {
               this.stopPolling();
               this.pollingInterval = setInterval(() => {
                    this.GetDevices();
                    this.GetDeviceResults({ status: "pending,matched" });
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
