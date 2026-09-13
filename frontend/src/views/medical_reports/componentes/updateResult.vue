<template>
     <Dialog
          v-model:visible="updateResultModalDialog"
          modal
          :header="t('updateResult')"
          style="width: 100rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <hr />
          <br />
          <div class="uploadFile">
               <div>
                    <div class="add d-flex">
                         <h3>رفع الملفات</h3>
                         <!-- Add Attachment Button -->
                         <Button
                              icon="pi pi-plus"
                              class="p-button-rounded p-button-success p-button-text"
                              @click="addAttachment" />
                    </div>
                    <!-- Table for attachments -->
                    <table class="table">
                         <thead>
                              <tr>
                                   <th>{{ t("name") }}</th>
                                   <th>{{ t("file") }}</th>
                                   <th>{{ t("delete") }}</th>
                              </tr>
                         </thead>
                         <tbody>
                              <tr v-for="(item, index) in attachments" :key="index">
                                   <td class="p-2">
                                        <InputText class="w-full" type="text" v-model="item.name" />
                                   </td>
                                   <td>
                                        <FileUpload
                                             class="w-full"
                                             required
                                             mode="basic"
                                             @select="(event) => onFileChange(event, index)"
                                             chooseLabel="ملف" />
                                   </td>
                                   <td>
                                        <Button
                                             icon="pi pi-trash"
                                             class="p-button-rounded p-button-danger p-button-text"
                                             @click="removeAttachment(index)" />
                                   </td>
                              </tr>
                         </tbody>
                    </table>
               </div>
          </div>
          <br />
          <hr />
          <!-- testes datails -->
          <div class="details">
               <h1>{{ t("tests") }}</h1>
               <TabView v-if="updateResultRecord.tests.length > 0">
                    <TabPanel :header="item.name" v-for="(item, index) in updateResultRecord.tests" :key="index">
                         <div>
                              <!-- Table for dynamic template -->
                                    <div v-if="item.sub_tests?.length > 0">
                                        <div v-if="selectedTemplate" style="direction: ltr;">
                                             <!-- ✅ Editable Dynamic Table -->
                                             <div v-html="selectedTemplate" class="dynamic-template"></div>
                                        </div>
                                   </div>
                                   
                              <table class="table" v-else>
                                   <thead>
                                        <tr>
                                             <th>{{ t("done") }}</th>
                                             <th>{{ t("tests-reference-ranges") }}</th>
                                             <th>{{ t("Unit") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("last_result") }}</th>
                                             <th>{{ t("Test_Group_Comment") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                             <th>{{ t("name") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr>
                                             <td>
                                                  <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                             </td>
                                             <td>
                                                  <span
                                                       v-for="range in getFilteredRanges(item.test_reference_ranges)"
                                                       :key="range.id">
                                                       <p>
                                            <div v-if="range.notes">
                                                       <p v-for="(line, index) in range.notes.split('\n')" :key="index">{{ line }}</p>
                                                       </div>
                                                       <p v-else>
                                                       {{ range.from + "-" + range.to }}
                                                       </p>
                                                       </p>
                                                       <p v-for="option in range.test_reference_options" :key="option">
                                                            <span>{{ option }}</span>
                                                       </p>
                                                  </span>
                                             </td>
                                             <td>{{ item.unit ?? "---" }}</td>
                                             <td>
                                                  <Dropdown
                                                       v-model="item.result_status_id_fk"
                                                       :options="resultStatus"
                                                       optionLabel="label"
                                                       optionValue="value"
                                                       :placeholder="t('select')" />
                                             </td>
                                             <td>
                                                  <template v-if="item.result_type_id_fk == 1">
                                                       <InputNumber
                                                            class="w-full"
                                                            type="number"
                                                            v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 2">
                                                       <InputNumber
                                                            class="w-full"
                                                            type="number"
                                                            v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 3">
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 4">
                                                       <Dropdown
                                                            v-model="item.result"
                                                            :options="item.selection_type_options"
                                                            :placeholder="t('select')" />
                                                  </template>
                                                  <template
                                                       v-else-if="
                                                            item.result_type_id_fk == null ||
                                                            item.result_type_id_fk == ''
                                                       ">
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                             </td>
                                             <td>
                                                  <InputText class="w-full" type="text" v-model="item.comment" />
                                             </td>
                                             <td>{{ item.price ?? 0 }}</td>
                                             <td>{{ item.name ?? "---" }}</td>
                                        </tr>
                                   </tbody>
                              </table>
                              <br />
                              <div>
                               
                                   <label>{{ t("Result_Comments") }}</label>

                                   <span>
                                        <MultiSelect
                                             v-model="updateResultRecord.tests_comment"
                                             :options="updateResultRecord.result_comments_tests"
                                             class="w-full mt-2" />
                                   </span>
                              </div>
                              <br />
                              <hr />

                              <br />
                         </div>
                    </TabPanel>
               </TabView>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>

               <!-- Packages Section -->
               <div style="margin-top: 40px;">
                    <hr />
                    <h2 style="color: #004e54;">{{ t("packages") }}</h2>
                    <TabView v-if="updateResultRecord.packages.length > 0">
                         <TabPanel :header="item.name" v-for="(item, index) in updateResultRecord.packages" :key="index">
                              <table class="table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                             <th>{{ t("Test_Group_Comment") }}</th>
                                             <th>{{ t("last_result") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                             <th>{{ t("done") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(item, index) in item.tests" :key="index">
                                             <td>{{ item.name }}</td>
                                             <td>{{ item.price ?? 0 }}</td>
                                             <td>
                                                  <InputText class="w-full" type="text" v-model="item.comment" />
                                             </td>
                                             <td>
                                                  <template v-if="item.result_type_id_fk == 1">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 2">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 3">
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 4">
                                                       <Dropdown
                                                            v-model="item.result"
                                                            :options="item.selection_type_options"
                                                            :placeholder="t('select')" />
                                                  </template>
                                                  <template v-else>
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                             </td>
                                             <td>
                                                  <Dropdown
                                                       v-model="item.result_status_id_fk"
                                                       :options="resultStatus"
                                                       optionLabel="label"
                                                       optionValue="value"
                                                       :placeholder="t('select')" />
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                             </td>
                                        </tr>
                                        <tr v-for="(item, index) in item.cultures" :key="index">
                                             <td>{{ item.name }}</td>
                                             <td>{{ item.price ?? 0 }}</td>
                                             <td>
                                                  <InputText class="w-full" type="text" v-model="item.comment" />
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                             </td>
                                             <td>
                                                  <template v-if="item.result_type_id_fk == 1">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 2">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 3">
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 4">
                                                       <Dropdown
                                                            v-model="item.result"
                                                            :options="item.selection_type_options"
                                                            :placeholder="t('select')" />
                                                  </template>
                                                  <template v-else>
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                             </td>
                                             <td>
                                                  <Dropdown
                                                       v-model="item.result_status_id_fk"
                                                       :options="resultStatus"
                                                       optionLabel="label"
                                                       optionValue="value"
                                                       :placeholder="t('select')" />
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <br />
                              <div>
                                   <label class="mb-2">{{ t("Result_Comments") }}</label>
                                   <span>
                                        <MultiSelect
                                             v-model="updateResultRecord.package_comment"
                                             :options="updateResultRecord.result_package_comments"
                                             class="w-full mt-2" />
                                   </span>
                              </div>
                              <br />
                         </TabPanel>
                    </TabView>
                    <div class="card flex justify-content-center mb-5" v-else>
                         <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                    </div>
               </div>

               <!-- Test Groups Section -->
               <div style="margin-top: 40px;">
                    <hr />
                    <h2 style="color: #004e54;">{{ t("test-groups") }}</h2>
                    <TabView v-if="updateResultRecord.test_groups.length > 0">
                         <TabPanel
                              :header="item.group_name"
                              v-for="(item, index) in updateResultRecord.test_groups"
                              :key="index">
                              <table class="table">
                                   <thead>
                                        <tr>
                                             <th>{{ t("name") }}</th>
                                             <th>{{ t("Original_Price") }}</th>
                                             <th>{{ t("Test_Group_Comment") }}</th>
                                             <th>{{ t("last_result") }}</th>
                                             <th>{{ t("Result") }}</th>
                                             <th>{{ t("Result_Type") }}</th>
                                             <th>{{ t("done") }}</th>
                                        </tr>
                                   </thead>
                                   <tbody>
                                        <tr v-for="(item, index) in item.tests" :key="index">
                                             <td>{{ item.name }}</td>
                                             <td>{{ item.price ?? 0 }}</td>
                                             <td>
                                                  <InputText class="w-full" type="text" v-model="item.comment" />
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                             </td>
                                             <td>
                                                  <template v-if="item.result_type_id_fk == 1">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 2">
                                                       <InputNumber class="w-full" type="number" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 3">
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                                  <template v-if="item.result_type_id_fk == 4">
                                                       <Dropdown
                                                            v-model="item.result"
                                                            :options="item.selection_type_options"
                                                            :placeholder="t('select')" />
                                                  </template>
                                                  <template v-else>
                                                       <InputText class="w-full" type="text" v-model="item.result" />
                                                  </template>
                                             </td>
                                             <td>
                                                  <Dropdown
                                                       v-model="item.result_status_id_fk"
                                                       :options="resultStatus"
                                                       optionLabel="label"
                                                       optionValue="value"
                                                       :placeholder="t('select')" />
                                             </td>
                                             <td>
                                                  <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                              <br />
                         </TabPanel>
                    </TabView>
                    <div class="card flex justify-content-center mb-5" v-else>
                         <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
                    </div>
               </div>
          </div>
          <br />
          <div class="details">
               <h1>{{ t("cultures") }}</h1>
               <TabView v-if="updateResultRecord.cultures.length > 0">
                    <TabPanel :header="item.name" v-for="(item, index) in updateResultRecord.cultures" :key="index">
                         <table class="table">
                              <thead>
                                   <tr>
                                        <th>{{ t("name") }}</th>
                                        <th>{{ t("Original_Price") }}</th>
                                        <th>{{ t("Test_Group_Comment") }}</th>
                                        <th>{{ t("last_result") }}</th>
                                        <th>{{ t("Result") }}</th>
                                        <th>{{ t("Result_Type") }}</th>
                                        <th>{{ t("done") }}</th>
                                        <th>{{ t("Show_Attributes") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr>
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.price ?? 0 }}</td>
                                        <td>
                                             <InputText class="w-full" type="text" v-model="item.comment" />
                                        </td>
                                        <td>
                                             <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                        </td>

                                        <td>
                                             <template v-if="item.result_type_id_fk == 1">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 2">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 3">
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 4">
                                                  <Dropdown
                                                       v-model="item.result"
                                                       :options="item.selection_type_options"
                                                       :placeholder="t('select')" />
                                             </template>
                                             <template v-else>
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                        </td>
                                        <td>
                                             <Dropdown
                                                  v-model="item.result_status_id_fk"
                                                  :options="resultStatus"
                                                  optionLabel="label"
                                                  optionValue="value"
                                                  :placeholder="t('select')" />
                                        </td>

                                        <td>
                                             <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                        </td>
                                        <td>
                                             <!-- Toggle button to show/hide attributes -->

                                             <i
                                                  :class="showAttributes[index] ? 'pi pi-angle-up' : 'pi pi-angle-down'"
                                                  style="color: slateblue; font-weight: bold"
                                                  @click="toggleAttributes(index)"></i>
                                        </td>
                                   </tr>
                                   <tr v-if="showAttributes[index]">
                                        <td colspan="9">
                                             <table class="table table-bordered attrTable">
                                                  <thead>
                                                       <tr>
                                                            <th>{{ t("name") }}</th>
                                                            <th>{{ t("Order") }}</th>
                                                            <th>{{ t("Result") }}</th>
                                                       </tr>
                                                  </thead>
                                                  <tbody>
                                                       <tr v-for="attribute in item.attribute" :key="attribute">
                                                            <td>{{ attribute.name }}</td>
                                                            <td>{{ attribute.order }}</td>

                                                            <td>
                                                                 <InputText
                                                                      v-if="attribute.result_type_id_fk == 1"
                                                                      class="w-full"
                                                                      required
                                                                      type="number"
                                                                      v-model="attribute.result" />

                                                                 <InputText
                                                                      v-if="attribute.result_type_id_fk == 3"
                                                                      class="w-full"
                                                                      required
                                                                      type="text"
                                                                      v-model="attribute.result" />

                                                                 <Dropdown
                                                                      v-if="attribute.result_type_id_fk == 4"
                                                                      v-model="attribute.result"
                                                                      :options="attribute.selection_type_options"
                                                                      :placeholder="t('select')" />
                                                                 <template v-else>
                                                                      <InputText
                                                                           class="w-full"
                                                                           type="text"
                                                                           v-model="attribute.result" />
                                                                 </template>
                                                            </td>
                                                       </tr>
                                                  </tbody>
                                             </table>
                                        </td>
                                   </tr>
                              </tbody>
                         </table>

                         <br />
                         <div>
                              <label class="mb-2">{{ t("Result_Comments") }}</label>
                              <span>
                                   <MultiSelect
                                        v-model="updateResultRecord.cultures_comment"
                                        :options="updateResultRecord.result_comments_cultures"
                                        class="w-full mt-2" />
                              </span>
                         </div>

                         <br />
                    </TabPanel>
               </TabView>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
          <br />
          <div class="details" style="display:none;">
               <h1>{{ t("packages") }}</h1>
               <TabView v-if="updateResultRecord.packages.length > 0">
                    <TabPanel :header="item.name" v-for="(item, index) in updateResultRecord.packages" :key="index">
                         <table class="table">
                              <thead>
                                   <tr>
                                        <th>{{ t("name") }}</th>
                                        <th>{{ t("Original_Price") }}</th>
                                        <th>{{ t("Test_Group_Comment") }}</th>
                                        <th>{{ t("last_result") }}</th>
                                        <th>{{ t("Result") }}</th>
                                        <th>{{ t("Result_Type") }}</th>
                                        <th>{{ t("done") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="(item, index) in item.tests" :key="index">
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.price ?? 0 }}</td>
                                        <td>
                                             <InputText class="w-full" type="text" v-model="item.comment" />
                                        </td>
                                        <td>
                                             <template v-if="item.result_type_id_fk == 1">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 2">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 3">
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 4">
                                                  <Dropdown
                                                       v-model="item.result"
                                                       :options="item.selection_type_options"
                                                       :placeholder="t('select')" />
                                             </template>
                                             <template v-else>
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                        </td>
                                        <td>
                                             <Dropdown
                                                  v-model="item.result_status_id_fk"
                                                  :options="resultStatus"
                                                  optionLabel="label"
                                                  optionValue="value"
                                                  :placeholder="t('select')" />
                                        </td>

                                        <td>
                                             <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                        </td>
                                   </tr>
                                   <tr v-for="(item, index) in item.cultures" :key="index">
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.price ?? 0 }}</td>
                                        <td>
                                             <InputText class="w-full" type="text" v-model="item.comment" />
                                        </td>
                                        <td>
                                             <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                        </td>
                                        <td>
                                             <template v-if="item.result_type_id_fk == 1">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 2">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 3">
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 4">
                                                  <Dropdown
                                                       v-model="item.result"
                                                       :options="item.selection_type_options"
                                                       :placeholder="t('select')" />
                                             </template>
                                             <template v-else>
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                        </td>
                                        <td>
                                             <Dropdown
                                                  v-model="item.result_status_id_fk"
                                                  :options="resultStatus"
                                                  optionLabel="label"
                                                  optionValue="value"
                                                  :placeholder="t('select')" />
                                        </td>

                                        <td>
                                             <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                         <br />
                         <div>
                              <label class="mb-2">{{ t("Result_Comments") }}</label>
                              <span>
                                   <MultiSelect
                                        v-model="updateResultRecord.package_comment"
                                        :options="updateResultRecord.result_package_comments"
                                        class="w-full mt-2" />
                              </span>
                         </div>

                         <br />
                    </TabPanel>
               </TabView>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
          <br />
          <div class="details" style="display:none;">
               <h1>{{ t("test-groups") }}</h1>
               <TabView v-if="updateResultRecord.test_groups.length > 0">
                    <TabPanel
                         :header="item.group_name"
                         v-for="(item, index) in updateResultRecord.test_groups"
                         :key="index">
                         <table class="table">
                              <thead>
                                   <tr>
                                        <th>{{ t("name") }}</th>
                                        <th>{{ t("Original_Price") }}</th>
                                        <th>{{ t("Test_Group_Comment") }}</th>
                                        <th>{{ t("last_result") }}</th>
                                        <th>{{ t("Result") }}</th>
                                        <th>{{ t("Result_Type") }}</th>
                                        <th>{{ t("done") }}</th>
                                   </tr>
                              </thead>
                              <tbody>
                                   <tr v-for="(item, index) in item.tests" :key="index">
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.price ?? 0 }}</td>
                                        <td>
                                             <InputText class="w-full" type="text" v-model="item.comment" />
                                        </td>
                                        <td>
                                             <Checkbox v-model="item.last_result" inputId="ingredient8" binary />
                                        </td>
                                        <td>
                                             <template v-if="item.result_type_id_fk == 1">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 2">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 3">
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 4">
                                                  <Dropdown
                                                       v-model="item.result"
                                                       :options="item.selection_type_options"
                                                       :placeholder="t('select')" />
                                             </template>
                                             <template v-else>
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                        </td>
                                        <td>
                                             <Dropdown
                                                  v-model="item.result_status_id_fk"
                                                  :options="resultStatus"
                                                  optionLabel="label"
                                                  optionValue="value"
                                                  :placeholder="t('select')" />
                                        </td>

                                        <td>
                                             <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                        </td>
                                   </tr>
                                   <tr v-for="(item, index) in item.cultures" :key="index">
                                        <td>{{ item.name }}</td>
                                        <td>{{ item.price ?? 0 }}</td>
                                        <td>
                                             <InputText class="w-full" type="text" v-model="item.comment" />
                                        </td>
                                        <td>
                                             <template v-if="item.result_type_id_fk == 1">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 2">
                                                  <InputNumber class="w-full" type="number" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 3">
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                             <template v-if="item.result_type_id_fk == 4">
                                                  <Dropdown
                                                       v-model="item.result"
                                                       :options="item.selection_type_options"
                                                       :placeholder="t('select')" />
                                             </template>
                                             <template v-else>
                                                  <InputText class="w-full" type="text" v-model="item.result" />
                                             </template>
                                        </td>
                                        <td>
                                             <Dropdown
                                                  v-model="item.result_status_id_fk"
                                                  :options="resultStatus"
                                                  optionLabel="label"
                                                  optionValue="value"
                                                  :placeholder="t('select')" />
                                        </td>

                                        <td>
                                             <Checkbox v-model="item.is_done" inputId="ingredient8" binary />
                                        </td>
                                   </tr>
                              </tbody>
                         </table>
                         <br />
                         <!-- <div>
                              <label class="mb-2">{{ t("Result_Comments") }}</label>
                              <span>
                                   <MultiSelect
                                        v-model="updateResultRecord.package_comment"
                                        :options="updateResultRecord.result_package_comments"
                                        class="w-full mt-2" />
                              </span>
                         </div> -->

                         <br />
                    </TabPanel>
               </TabView>
               <div class="card flex justify-content-center mb-5" v-else>
                    <InlineMessage severity="info">{{ t("noData") }}</InlineMessage>
               </div>
          </div>
          <br />
          <div class="comment">
               <label for="">{{ t("resultComment") }}</label>
               <InputText class="w-full mt-3" type="text" v-model="updateResultRecord.comments"></InputText>
          </div>
          <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
               <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
               <Button size="small" :label="t('update')" severity="success" @click="update()"></Button>
          </div>
     </Dialog>
</template>
<script>
     import { mapActions, mapWritableState } from "pinia";
     import { useTemplatesStore } from "@/store/modules/template";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { useresultStatusStore } from "@/store/modules/result-status";
     export default {
          data() {
               return {
                    attachments: [{ name: "", file: null }],
                    showAttributes: [],
                    template: {},
                    selectedTemplateId: null, // Currently selected template ID
                    selectedTemplate: null, // Loaded template content
                    templateFields: {}, // Store editable fields
                    
               };
          },
          computed: {
               ...mapWritableState(useTemplatesStore, ["templates", "record", "dialog", "pagination"]),
               ...mapWritableState(useresultStatusStore, ["resultStatus"]),
               ...mapWritableState(useinvoicesStore, [
                    "updateResultRecord",
                    "updateResultModalDialog",
                    "testQuesions",
                    "cultursComment",
                    "testsComment",
                    "package_comment",
               ]),
          },
          mounted() {
               this.GetTemplates();
               this.GetresultStatus();
          }, 

          methods: {
               ...mapActions(useresultStatusStore, ["GetresultStatus"]),
               ...mapActions(useinvoicesStore, ["updateResult", "questions"]),
               ...mapActions(useTemplatesStore, ["GetTemplates"]),
                                        injectTableStyles() {
                              // Prevent duplicate style injection
                              if (document.getElementById("custom-table-styles")) return;

                              const style = document.createElement("style");
                              style.id = "custom-table-styles";
                              style.innerHTML = `
                                   #custom-table-container table {
                                        width: 100%;
                                        border-collapse: collapse;
                                        margin-top: 10px;
                                        background: #fff;
                                   }
                                   #custom-table-container th, 
                                   #custom-table-container td {
                                        border: 1px solid #ccc;
                                        padding: 10px;
                                        text-align: center;
                                   }
                                   #custom-table-container th {
                                        background: #f8f9fa;
                                        font-weight: bold;
                                   }
                                   #custom-table-container .editable-field {
                                        width: 90%;
                                        padding: 5px;
                                        border: 1px solid #ddd;
                                        font-size: 14px;
                                        text-align: center;
                                        background-color: white;
                                        color: black;
                                   }
                              `;
                              document.head.appendChild(style);
                         },
                         
                         async loadTemplate() {
  try {
    const testData = this.updateResultRecord.tests[0];
    let templateHtml = testData.content.html || "<p>لا يوجد قالب</p>";

    // ⚡️ استبدال كل المتغيرات التابعة لـ sub_tests
    if (Array.isArray(testData.sub_tests)) {
     testData.sub_tests.forEach((sub, index) => {
  const regex = new RegExp(`{{sub_test\\.${sub.name}\\.value}}`, "g");

  let replacement = "";

  if (sub.type === 4 && Array.isArray(sub.sup_test_reference_options) && sub.sup_test_reference_options.length > 0) {
    // ✅ SELECT لو نوع التحليل اختياري
    replacement = `
      <select 
        class="editable-field"
        data-key="sub_tests[${index}].value"
        data-test-id="${testData.test_id_fk}">
        ${sub.sup_test_reference_options.map(option => `
          <option value="${option}" ${option === sub.value ? "selected" : ""}>
            ${option}
          </option>
        `).join("")}
      </select>
    `;
  } else {
    // ✅ INPUT افتراضي
    replacement = `
      <input 
        type="text"
        class="editable-field"
        value="${sub.value || ""}" 
        data-key="sub_tests[${index}].value" 
        data-test-id="${testData.test_id_fk}" />
    `;
  }

  // 🧠 استبدال كل الـ placeholder بـ العنصر المناسب
  templateHtml = templateHtml.replace(regex, replacement);
});

    }

    // ✅ تخزين القالب داخل selectedTemplate
    this.selectedTemplate = `
      <div id="custom-table-container">
        ${templateHtml}
      </div>
    `;

    // ✅ بعد التحديث، فعل الاستماع لتحديث القيم
    this.$nextTick(() => {
      const vm = this;

      document.querySelectorAll(".editable-field").forEach(input => {
        input.addEventListener("input", e => {
          const key = e.target.dataset.key;
          const value = e.target.value;
          const match = key.match(/sub_tests\[(\d+)\]\.(.+)/);

          if (match) {
            const index = parseInt(match[1]);
            const field = match[2];
            const mainTest = vm.updateResultRecord.tests[0];

            if (mainTest && mainTest.sub_tests[index]) {
              mainTest.sub_tests[index][field] = value;
            }
          }
        });
      });
    });

    this.injectTableStyles(); // 💅 إذا عندك CSS خاص
  } catch (error) {
    console.error("❌ Error loading template:", error);
  }
},


                         
               getFilteredRanges(referenceRanges) {
                    const patientDetails = this.updateResultRecord?.patient;

                    const convertToDays = (age, unit) => {
                         switch (unit) {
                              case "Days":
                                   return age; // Already in days
                              case "Months":
                                   return age * 30; // Approximation: 1 month = 30 days
                              case "Years":
                                   return age * 365; // Approximation: 1 year = 365 days
                              default:
                                   return age; // Fallback if unit is unknown
                         }
                    };

                    const patientAgeInDays = convertToDays(patientDetails.age, patientDetails.age_unit);

                    return referenceRanges.filter((range) => {
                         const isGenderMatch = range.gender.toLowerCase() === patientDetails.gender.toLowerCase();

                         const rangeAgeFromInDays = convertToDays(range.age_from, range.age_unit);
                         const rangeAgeToInDays = convertToDays(range.age_to, range.age_unit);

                         const isAgeMatch =
                              patientAgeInDays >= rangeAgeFromInDays && patientAgeInDays <= rangeAgeToInDays;

                         return isGenderMatch && isAgeMatch;
                    });
               },
               addAttachment() {
                    // Add a new empty object for each new row
                    this.attachments.push({ name: "", file: null });
               },
               toggleAttributes(index) {
                    // Toggle the visibility of attributes table for the item at the given index
                    this.showAttributes[index] = !this.showAttributes[index];
               },
               removeAttachment(index) {
                    // Remove the attachment at the specified index
                    this.attachments.splice(index, 1);
               },
               update() {
                    /**
                     * Updates the attachments property of the updateResultRecord object.
                     * 
                     * This code maps over the attachments array and creates a new array of objects,
                     * each containing the name and file properties from the original items.
                     * If the name or file properties are not present, they are set to null.
                     * If the attachments array is empty or undefined, an empty array is assigned.
                     * 
                     *  attachments - The array of attachment objects to be processed.
                     *  updateResultRecord - The object where the processed attachments will be stored.
                     updateResultRecord.attachments - The processed array of attachment objects.
                     */
                    //strore multi file
                    this.updateResultRecord.attachments =
                         this.attachments.map((item) => ({
                              name: item.name ? item.name : null,
                              file: item.file ? item.file : null,
                         })) ?? [];
                    this.updateResultRecord.tests = this.updateResultRecord.tests;

                    this.updateResultRecord.cultures = this.updateResultRecord.cultures;
                    this.updateResultRecord.packages = this.updateResultRecord.packages;
                    this.updateResultRecord.test_groups = this.updateResultRecord.test_groups;

                    this.updateResult().then(() => {
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.updateResultRecord);
                         this.testsComment = [];
                         this.cultursComment = [];
                         this.updateResultModalDialog = false;
                    });
               },
               onFileChange(event, index) {
                    const file = event.files ? event.files[0] : null;

                    if (file) {
                         // Initialize attachment object if not already initialized
                         this.attachments[index].file = file; // Ensure it's an object with a 'file' key
                    }
               },
               close() {
                    this.updateResultModalDialog = false;
                    // this.clearObjectValues(this.updateResultRecord);
               },
          },
watch: {
    // ✅ Watch when `dialog` opens
    updateResultModalDialog(val) {
        if (val) {
            console.log("✅ Dialog Opened");

            // Wait for `updateResultRecord.tests` to be populated
            this.$nextTick(() => {
                if (this.updateResultRecord.tests && this.updateResultRecord.tests.length > 0) {
                    this.selectedTemplateId = this.updateResultRecord.tests[0].test_id_fk;
                    console.log("🚀 Calling loadTemplate() with ID:", this.selectedTemplateId);
                    this.loadTemplate();
                } else {
                    console.warn("⚠️ updateResultRecord.tests is empty!");
                }
            });
        }
    },
},
     };
</script>
<style scoped>

.dynamic-template table {

    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #fff;
}

.dynamic-template th, 
.dynamic-template td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: left;
}

.dynamic-template th {
    background: #f8f9fa;
    font-weight: bold;
}

.dynamic-template p {
    margin: 5px 0;
    font-size: 14px;
}

.dynamic-template .notes {
    font-style: italic;
    color: #555;
}

/* ✅ Make Editable Fields Look Good */
.editable-field {
    width: 100%;
    padding: 5px;
    border: 1px solid #ddd;
    font-size: 14px;
}

.debug-container {
    margin-top: 20px;
    background: #f9f9f9;
    padding: 10px;
    border: 1px solid #ddd;
}

     .add {
          display: flex;
          justify-content: space-between;
     }
     .flex {
          display: flex !important;
          justify-content: space-between;
          flex-direction: row-reverse;
     }
     .checkBox {
          display: flex;
     }
     .details {
          padding: 30px;
          border: 2px solid #24683c69;
          border-radius: 15px;
     }

     /* Table Styling */
     tr {
          height: 55px;
     }
     td {
          border: 1px solid #0000002b;
     }
     thead {
          background: #374151;
          color: white;
          height: 35px;
     }
     .attrTable thead {
          background: #307b58;
     }
     .table {
          width: 100%;
          text-align: center;
          background: #f9fafb;
          display: table;
     }

     .add_payment {
          display: flex;
          justify-content: space-between;
          align-items: baseline;
     }
     .prece {
          position: relative;
          .span {
               position: absolute;
               left: 0;
               background: #d1d5db;
               width: 20px;
               text-align: center;
               font-size: x-large;
               height: 100%;
          }
     }
     .input-name {
          position: relative;
     }
     .pi-spin {
          color: #004e54bd;
     }
     .pi-search {
          color: darkgray;
     }
     .pi-spin,
     .pi-search {
          font-size: 1.5rem;
          position: absolute;
          top: 10px;
     }
     .ul {
          position: absolute;
          z-index: 2;
          left: 17px;
          right: 17px;

          background: white;
     }
     .rtl {
          left: 8px;
     }
     .ltr {
          right: 8px;
     }
     caption {
          background: #a5c1af;
          text-align: center;
          padding: 9px;
          font-weight: bold;
          color: #374151;
     }
     .p-dropdown {
          min-width: 40%;
     }
</style>
