<template>
     <Dialog
          v-model:visible="dialog"
          modal
          :header="record?.id ? t('update') : t('add')"
          style="width: 90rem"
          :style="lang == 'en' ? 'direction:ltr ' : 'direction: rtl'">
          <form @submit.prevent="record.id ? update() : create()" class="border-top-1 border-bluegray-100">
               <!-- pationt -->
               <br />
               <div class="details">
                    <div class="add d-flex">
                         <h3>{{ t("Patient_details") }}</h3>
                         <Button
                              size="small"
                              :class="showAddPatientForm ? 'p-button-danger' : 'p-button-success'"
                              :icon="showAddPatientForm ? 'pi pi-times' : 'pi pi-plus'"
                              :label="showAddPatientForm ? t('cancel') : t('addPatient')"
                              @click="toggleAddPatientForm"></Button>
                    </div>
                    <hr />

                    <!-- Add Patient Form (Expanded) -->
                    <div v-if="showAddPatientForm" class="add-patient-form mb-3">
                         <div class="grid mt-1">
                              <div class="col-6 pb-0 input-name">
                                   <label class="block text-md mb-2">{{ t("name") }}</label>
                                   <div style="position: relative">
                                        <InputText
                                             class="w-full"
                                             :placeholder="t('name')"
                                             required
                                             type="text"
                                             v-model="patientRecord.name"
                                             @input="searchPatientByname(patientRecord.name)" />
                                        <i
                                             class="pi pi-spin pi-spinner"
                                             v-show="hideLoading && isAddPatientNameShow"
                                             :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                                   </div>
                                   <div class="ul" v-show="isAddPatientNameShow">
                                        <Listbox
                                             v-if="searchNameTotalCount > 0"
                                             listStyle="max-height:250px"
                                             v-model="selectedPatientFromSearch"
                                             :options="searchRecords"
                                             optionLabel="name"
                                             class="w-full md:w-56 list" />
                                        <InlineMessage v-else class="w-full" severity="info" v-show="hideLoading">
                                             {{ t("noData") }}
                                        </InlineMessage>
                                   </div>
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("email") }}</label>
                                   <InputText class="w-full" type="email" v-model="patientRecord.email" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("phone_number") }}</label>
                                   <InputText minlength="11" maxlength="11" class="w-full" type="number" v-model="patientRecord.phone" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("dob") }}</label>
                                   <InputText class="w-full" type="date" v-model="patientRecord.dob" />
                              </div>
                              <div class="col-4 pb-0">
                                   <label class="block text-md mb-2">{{ t("age") }}</label>
                                   <InputText required class="w-full" type="number" v-model="patientRecord.age" />
                              </div>
                              <div class="col-4 pb-0">
                                   <label class="block text-md mb-2">{{ t("age_unit") }}</label>
                                   <Dropdown
                                        required
                                        class="w-full"
                                        v-model="patientRecord.age_unit_id_fk"
                                        :options="AgeUnits"
                                        optionLabel="label"
                                        optionValue="value"
                                        @change="ageChange(patientRecord.age_unit_id_fk)" />
                                   <Message severity="error" v-if="age_unitErrormessage">{{ age_unitErrormessage }}</Message>
                              </div>
                              <div class="col-4 pb-0">
                                   <label class="block text-md mb-2">{{ t("gender") }}</label>
                                   <Dropdown
                                        required
                                        class="w-full"
                                        v-model="patientRecord.gender_type_id_fk"
                                        :options="genders"
                                        @change="changeTitle(patientRecord.gender_type_id_fk)"
                                        optionLabel="label"
                                        optionValue="value" />
                                   <Message severity="error" v-if="genderErrormessage">{{ genderErrormessage }}</Message>
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("title") }}</label>
                                   <Dropdown
                                        required
                                        class="w-full"
                                        v-model="patientRecord.title_id_fk"
                                        :options="titles"
                                        optionLabel="label"
                                        optionValue="value"
                                        @change="titleChange(patientRecord.title_id_fk)" />
                                   <Message severity="error" v-if="titleErrormessage">{{ titleErrormessage }}</Message>
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("nationality") }}</label>
                                   <Dropdown
                                        filter
                                        class="w-full"
                                        v-model="patientRecord.nationality_id_fk"
                                        :options="nationalities"
                                        optionLabel="label"
                                        optionValue="value" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("national_id_no") }}</label>
                                   <InputText class="w-full" type="text" v-model="patientRecord.national_id_no" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("address") }}</label>
                                   <InputText class="w-full" type="text" v-model="patientRecord.address" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("passport_no") }}</label>
                                   <InputText class="w-full" type="number" v-model="patientRecord.passport_no" />
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("contract") }}</label>
                                   <Dropdown
                                        class="w-full"
                                        v-model="patientRecord.contract_id_fk"
                                        :options="contracts"
                                        optionLabel="label"
                                        optionValue="value" />
                              </div>
                              <div class="col-6 pb-0" style="display: flex; align-items: flex-end;">
                                   <Button
                                        size="small"
                                        :label="t('save')"
                                        icon="pi pi-check"
                                        class="p-button-success save-patient-btn"
                                        @click="createNewPatient"></Button>
                              </div>
                              <div class="col-6 pb-0">
                                   <label class="block text-md mb-2">{{ t("addfile") }}</label>
                                   <InputText
                                        class="w-full"
                                        type="file"
                                        @change="onFileChange($event)"
                                        accept=".png ,.jpg ,.jpeg" />
                              </div>
                         </div>
                         <hr />
                    </div>

                    <!-- Search Patient Form (Default) -->
                    <div v-else class="grid mt-1">
                         <div class="col-3 col-md-3 pb-0 input-name">
                              <label class="block text-md mb-2">{{ t("name") }}</label>
                              <div style="position: relative">
                                   <InputText
                                        filter
                                        class="w-full"
                                        :placeholder="t('search')"
                                        required
                                        type="text"
                                        v-model="patient.name"
                                        @input="searchitemByname(patient?.name)" />
                                   <i
                                        class="pi pi-spin pi-spinner"
                                        v-show="hideLoading && isNameShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                                   <i
                                        class="pi pi-search"
                                        v-show="!hideLoading && !isCodeShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                              </div>

                              <div class="ul" v-show="isNameShow">
                                   <Listbox
                                        v-if="searchNameTotalCount > 0"
                                        listStyle="max-height:250px"
                                        v-model="patient"
                                        :options="searchRecords"
                                        optionLabel="name"
                                        class="w-full md:w-56 list" />

                                   <InlineMessage v-else-if="showInlineMessage" class="w-full" severity="info">
                                        {{ t("noData") }}
                                   </InlineMessage>
                              </div>
                         </div>

                         <div class="col-3 pb-0 col-md-3input-name">
                              <label class="block text-md mb-2">{{ t("phone_number") }}</label>
                              <div style="position: relative">
                                   <InputText
                                        filter
                                        class="w-full"
                                        :placeholder="t('search')"
                                        type="text"
                                        v-model="patient.phone"
                                        @input="searchitemByphone(patient.phone)" />
                                   <i
                                        class="pi pi-spin pi-spinner"
                                        v-show="hideLoading && isphoneShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                                   <i
                                        class="pi pi-search"
                                        v-show="!hideLoading && !isphoneShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                              </div>

                              <div class="ul" v-show="isphoneShow">
                                   <Listbox
                                        v-if="searchPhoneTotalCount > 0"
                                        listStyle="max-height:250px"
                                        v-model="patient"
                                        :options="searchRecords"
                                        optionLabel="name"
                                        class="w-full md:w-56 list" />
                                   <InlineMessage v-else-if="showInlineMessage" class="w-full" severity="info">
                                        {{ t("noData") }}
                                   </InlineMessage>
                              </div>
                         </div>
                         <div class="col-3 pb-0 col-md-3 input-name">
                              <label class="block text-md mb-2">{{ t("code") }}</label>
                              <div style="position: relative">
                                   <InputText
                                        filter
                                        class="w-full"
                                        :placeholder="t('search')"
                                        required
                                        type="text"
                                        v-model="patient.code"
                                        @input="searchitemBycode(patient.code)" />
                                   <i
                                        class="pi pi-spin pi-spinner"
                                        v-show="hideLoading && isCodeShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                                   <i
                                        class="pi pi-search"
                                        v-show="!hideLoading && !isCodeShow"
                                        :class="lang == 'ar' ? 'rtl' : 'ltr'"></i>
                              </div>

                              <div class="ul" v-show="isCodeShow">
                                   <Listbox
                                        v-if="searchCodeTotalCount > 0"
                                        listStyle="max-height:250px"
                                        v-model="patient"
                                        :options="searchRecords"
                                        optionLabel="name"
                                        class="w-full md:w-56 list" />
                                   <InlineMessage v-else-if="showInlineMessage" class="w-full" severity="info">
                                        {{ t("noData") }}
                                   </InlineMessage>
                              </div>
                         </div>
                         <div class="col-1 pb-0">
                              <label class="block text-md mb-2">{{ t("age") }}</label>
                              <InputText class="w-full" disabled type="text" v-model="patient.age" />
                         </div>
                         <div class="col-1 pb-0">
                              <label class="block text-md mb-2">{{ t("gender") }}</label>
                              <InputText class="w-full" disabled type="text" v-model="patient.gender" />
                         </div>
                         <div class="col-3 pb-0 checkBox">
                              <Checkbox v-model="record.show_patient_card_id" inputId="ingredient2" binary />
                              <label class="block text-md mr-2 ml-2">{{ t("Show_passport_no") }}</label>
                         </div>
                         <div class="col-3 pb-0 checkBox">
                              <Checkbox v-model="record.show_patient_pic" inputId="ingredient2" binary />

                              <label class="block text-md mr-2 ml-2">{{ t("Show_avatar") }}</label>
                         </div>
                    </div>
               </div>

               <br />
               <!-- testes datails -->
               <div class="details">
                    <div class="add d-flex">
                         <h3>{{ t("tests") }}</h3>
                    </div>
                    <hr />
                    <TabView>
                         <TabPanel :header="t('tests')">
                              <div>
                                   <!-- Dropdowns for tests, packages, and cultures -->

                                   <div
                                        class="dropdowns"
                                        style="width: 100%; display: flex; justify-content: space-between">
                                        <!-- <Dropdown
                                             filter
                                             v-model="selectedTest"
                                             :options="tests"
                                             optionLabel="name"
                                             @change="addSelection('test', selectedTest)"
                                             :placeholder="t('select')"
                                             @scroll="onScroll" /> -->
                                             <Dropdown
                                                  v-model="selectedTest"
                                                  :options="tests"
                                                  :filter="true"
                                                  :filterBy="'name'" 
                                                  :placeholder="t('select')"
                                                  optionLabel="name"
                                                  @change="addSelection('test', selectedTest)"
                                                  @filter="onFilter"
                                                  @scroll="onScroll"
                                                  />
                                   </div>
                                   <br />

                                   <!-- Table for Tests -->

                                   <table class="table" v-if="selectedTests?.length">
                                        <thead>
                                             <tr>
                                                  <th>{{ t("name") }}</th>
                                                  <th>{{ t("Original_Price") }}</th>
                                                  <th>{{ t("to_lab") }}</th>
                                                  <th>{{ t("Sample_received") }}</th>
                                                  <th>
                                                       <Button
                                                            label="show Questions"
                                                            class="p-button-success"
                                                            @click="show_Questions()" />
                                                  </th>
                                             </tr>
                                        </thead>
                                        <tbody>
                                             <tr v-for="(item, index) in selectedTests" :key="index">
                                                  <td>{{ item.name }}</td>
                                                  <td>
                                                       {{
                                                            (price_list_id_fk &&
                                                                 item.prices?.find(
                                                                      (p) => p.price_list_id === price_list_id_fk
                                                                 )?.price_for_customer) ||
                                                            item.price ||
                                                            item.original_price ||
                                                            0
                                                       }}
                                                  </td>

                                                  <td>
                                                       <Dropdown
                                                            v-model="item.to_lab"
                                                            :options="fromLap"
                                                            optionLabel="name"
                                                            optionValue="id"
                                                            :placeholder="t('select')" />
                                                  </td>

                                                  <td>
                                                       <Checkbox
                                                            v-model="item.is_sample_received"
                                                            inputId="ingredient3"
                                                            binary />
                                                  </td>
                                                  <td>
                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text"
                                                            @click="removeSelection('test', index)" />
                                                  </td>
                                             </tr>
                                        </tbody>
                                   </table>
                                   <br />
                                   <hr />
                                   <!-- questions -->

                                   <div class="allQuestions" v-if="testQuesions.length > 0">
                                        <div class="questions">
                                             <tabel class="table">
                                                  <!-- <caption v-if="record.id">{{ item.test_name }}</caption> -->
                                                  <!-- <caption v-if="!record.id">
                                                       {{
                                                            selectedTests
                                                                 ? selectedTests?.find((v) => v.id === item.test_id_fk)
                                                                        ?.name
                                                                 : ""
                                                       }}
                                                  </caption> -->
                                                  <thead>
                                                       <th>{{ t("question") }}</th>
                                                       <th>{{ t("answer") }}</th>
                                                  </thead>
                                                  <tbody>
                                                       <!-- <template
                                                            v-for="(item, itemIndex) in testQuesions"
                                                            :key="itemIndex"> -->
                                                       <tr v-for="(question, index) in testQuesions" :key="index">
                                                            <td>
                                                                 {{ question.question }}
                                                                 <InputText
                                                                      class="w-full"
                                                                      type="text"
                                                                      v-model="question.id"
                                                                      hidden
                                                                      disabled></InputText>
                                                            </td>

                                                            <td>
                                                                 <Checkbox
                                                                      v-model="question.answer"
                                                                      inputId="ingredient8"
                                                                      binary
                                                                      v-if="question.answer_type == 'checkbox'" />
                                                                 <InputText
                                                                      v-if="question.answer_type == 'number'"
                                                                      class="w-full"
                                                                      required
                                                                      type="number"
                                                                      v-model="question.answer" />
                                                                 <InputText
                                                                      v-if="question.answer_type == 'date'"
                                                                      class="w-full"
                                                                      required
                                                                      type="date"
                                                                      v-model="question.answer" />
                                                                 <InputText
                                                                      v-if="question.answer_type == 'text'"
                                                                      class="w-full"
                                                                      required
                                                                      type="text"
                                                                      v-model="question.answer" />

                                                                 <Dropdown
                                                                      v-if="question.answer_type == 'selection'"
                                                                      v-model="question.answer"
                                                                      :options="question.answer_type_selection_values"
                                                                      :placeholder="t('select')" />
                                                            </td>
                                                       </tr>
                                                       <!-- </template> -->
                                                  </tbody>
                                             </tabel>
                                             <br />
                                        </div>
                                   </div>
                              </div>
                         </TabPanel>
                         <TabPanel :header="t('cultures')">
                              <div>
                                   <div class="cultures">
                                        <Dropdown
                                             filter
                                             v-model="selectedCulture"
                                             :options="cultures"
                                             optionLabel="name"
                                             @change="addSelection('culture', selectedCulture)"
                                             :placeholder="t('select')" />
                                   </div>
                                   <br />

                                   <table class="table" v-if="selectedCultures?.length">
                                        <thead>
                                             <tr>
                                                  <th>{{ t("name") }}</th>
                                                  <th>{{ t("Original_Price") }}</th>

                                                  <th>{{ t("to_lab") }}</th>
                                                  <th>{{ t("Sample_received") }}</th>
                                                  <th></th>
                                                  <th></th>
                                             </tr>
                                        </thead>
                                        <tbody>
                                             <tr v-for="(item, index) in selectedCultures" :key="index">
                                                  <td>{{ item.name }}</td>
                                                  <td>
                                                       {{
                                                            (price_list_id_fk &&
                                                                 item.prices?.find(
                                                                      (p) => p.price_list_id === price_list_id_fk
                                                                 )?.price_for_customer) ||
                                                            item.price ||
                                                            item.original_price ||
                                                            0
                                                       }}
                                                  </td>

                                                  <td>
                                                       <Dropdown
                                                            v-model="item.to_lab"
                                                            :options="fromLap"
                                                            optionLabel="name"
                                                            optionValue="id"
                                                            :placeholder="t('select')" />
                                                  </td>

                                                  <td>
                                                       <Checkbox
                                                            v-model="item.is_sample_received"
                                                            inputId="ingredient3"
                                                            binary />
                                                  </td>
                                                  <td>
                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text"
                                                            @click="removeSelection('culture', index)" />
                                                  </td>
                                             </tr>
                                        </tbody>
                                   </table>
                              </div>
                         </TabPanel>
                         <TabPanel :header="t('packages')">
                              <div>
                                   <div>
                                        <Dropdown
                                             filter
                                             v-model="selectedPackage"
                                             :options="packagesList"
                                             optionLabel="name"
                                             @change="addSelection('package', selectedPackage)"
                                             :placeholder="t('select')" />
                                   </div>
                                   <br />
                                   <table class="table" v-if="selectedPackages?.length">
                                        <thead>
                                             <tr>
                                                  <th>{{ t("name") }}</th>
                                                  <th>{{ t("Original_Price") }}</th>

                                                  <th>{{ t("to_lab") }}</th>
                                                  <th>{{ t("Sample_received") }}</th>
                                                  <th>{{ t("the_tests") }}</th>

                                                  <th></th>
                                             </tr>
                                        </thead>
                                        <tbody>
                                             <template v-for="(item, index) in selectedPackages" :key="index">
                                                  <tr>
                                                       <td>{{ item.name }}</td>
                                                       <td>
                                                            {{
                                                                 (price_list_id_fk &&
                                                                      item.prices?.find(
                                                                           (p) => p.price_list_id === price_list_id_fk
                                                                      )?.price_for_customer) ||
                                                                 item.price ||
                                                                 item.original_price ||
                                                                 0
                                                            }}
                                                       </td>

                                                       <td>
                                                            <Dropdown
                                                                 v-model="item.lab"
                                                                 :options="fromLap"
                                                                 optionLabel="name"
                                                                 optionValue="id"
                                                                 :placeholder="t('select')" />
                                                       </td>

                                                       <td>
                                                            <Checkbox
                                                                 v-model="item.is_sample_received"
                                                                 inputId="ingredient3"
                                                                 binary />
                                                       </td>
                                                       <td>
                                                            <i
                                                                 :class="
                                                                      showTests[index]
                                                                           ? 'pi pi-angle-up'
                                                                           : 'pi pi-angle-down'
                                                                 "
                                                                 style="color: slateblue; font-weight: bold"
                                                                 @click="toggletests(index)"></i>
                                                       </td>
                                                       <td>
                                                            <Button
                                                                 icon="pi pi-trash"
                                                                 class="p-button-rounded p-button-danger p-button-text"
                                                                 @click="removeSelection('package', index)" />
                                                       </td>
                                                  </tr>
                                                  <!-- Expanded Tests and Cultures Row -->

                                                  <tr
                                                       style="background: #3b82f62b"
                                                       v-show="showTests[index]"
                                                       v-for="test in item.tests"
                                                       :key="`test-${test.id}`">
                                                       <td>
                                                            {{ test.name }}
                                                       </td>
                                                       <td>
                                                            {{ test.price }}
                                                       </td>
                                                       <td>
                                                            {{ t("tests") }}
                                                       </td>
                                                       <td colspan="2"></td>
                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                            @click="removeTest(index, test.id)" />
                                                  </tr>
                                                  <tr
                                                       style="background: #3b82f62b"
                                                       v-show="showTests[index]"
                                                       v-for="culture in item.cultures"
                                                       :key="`test-${culture.id}`">
                                                       <td>
                                                            {{ culture.name }}
                                                       </td>
                                                       <td>
                                                            {{ culture.price }}
                                                       </td>
                                                       <td>
                                                            {{ t("cultures") }}
                                                       </td>
                                                       <td colspan="2"></td>

                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                            @click="removeCulture(index, culture.id)" />
                                                  </tr>

                                                  <!-- <tr v-if="showTests[index]">
                                                       <td colspan="6">
                                                            <div class="p-4">
                                                                 <div>
                                                                      <div
                                                                           v-if="item.tests && item.tests.length"
                                                                           class="mt-2">
                                                                           <div
                                                                                v-for="test in item.tests"
                                                                                :key="`test-${test.id}`"
                                                                                class="flex items-center justify-between mb-2">
                                                                                <span>{{ test.name }}</span>
                                                                                <Button
                                                                                     icon="pi pi-trash"
                                                                                     class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                                                     @click="
                                                                                          removeTest(index, test.id)
                                                                                     " />
                                                                           </div>
                                                                      </div>
                                                                      <div v-else>{{ t("noData") }}</div>
                                                                 </div>

                                                                 <div class="mt-4">
                                                                      <div
                                                                           v-if="item.cultures && item.cultures.length"
                                                                           class="mt-2">
                                                                           <div
                                                                                v-for="culture in item.cultures"
                                                                                :key="`culture-${culture.id}`"
                                                                                class="flex items-center justify-between mb-2">
                                                                                <span>{{ culture.name }}</span>
                                                                                <Button
                                                                                     icon="pi pi-trash"
                                                                                     class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                                                     @click="
                                                                                          removeCulture(
                                                                                               index,
                                                                                               culture.id
                                                                                          )
                                                                                     " />
                                                                           </div>
                                                                      </div>
                                                                      <div v-else>{{ t("noData") }}</div>
                                                                 </div>
                                                            </div>
                                                       </td>
                                                  </tr> -->
                                             </template>
                                        </tbody>
                                   </table>
                              </div>
                         </TabPanel>
                         <TabPanel :header="t('test-groups')">
                              <div>
                                   <div>
                                        <Dropdown
                                             filter
                                             v-model="selectedtestGroup"
                                             :options="testGroups"
                                             optionLabel="group_name"
                                             @change="addSelection('testGroup', selectedtestGroup)"
                                             :placeholder="t('select')" />
                                   </div>
                                   <br />
                                   <table class="table" v-if="selectedtestGroups?.length">
                                        <thead>
                                             <tr>
                                                  <th>{{ t("name") }}</th>
                                                  <th>{{ t("Original_Price") }}</th>
                                                  <th>{{ t("to_lab") }}</th>
                                                  <th>{{ t("Sample_received") }}</th>
                                                  <th>{{ t("the_tests") }}</th>

                                                  <th></th>
                                             </tr>
                                        </thead>
                                        <tbody>
                                             <template v-for="(item, index) in selectedtestGroups" :key="index">
                                                  <tr>
                                                       <td>{{ item.group_name }}</td>
                                                       <td>
                                                            {{
                                                                 (price_list_id_fk &&
                                                                      item.prices?.find(
                                                                           (p) => p.price_list_id === price_list_id_fk
                                                                      )?.price_for_customer) ||
                                                                 item.original_price ||
                                                                 0
                                                            }}
                                                       </td>

                                                       <td>
                                                            <Dropdown
                                                                 v-model="item.to_lab"
                                                                 :options="fromLap"
                                                                 optionLabel="name"
                                                                 optionValue="id"
                                                                 :placeholder="t('select')" />
                                                       </td>

                                                       <td>
                                                            <Checkbox
                                                                 v-model="item.is_sample_received"
                                                                 inputId="ingredient3"
                                                                 binary />
                                                       </td>
                                                       <td>
                                                            <i
                                                                 :class="
                                                                      showTests[index]
                                                                           ? 'pi pi-angle-up'
                                                                           : 'pi pi-angle-down'
                                                                 "
                                                                 style="color: slateblue; font-weight: bold"
                                                                 @click="toggletests(index)"></i>
                                                       </td>
                                                       <td>
                                                            <Button
                                                                 icon="pi pi-trash"
                                                                 class="p-button-rounded p-button-danger p-button-text"
                                                                 @click="removeSelection('testGroup', index)" />
                                                       </td>
                                                  </tr>
                                                  <!-- Expanded Tests and Cultures Row -->

                                                  <tr
                                                       style="background: #3b82f62b"
                                                       v-show="showTests[index]"
                                                       v-for="test in item.tests"
                                                       :key="`test-${test.id}`">
                                                       <td>
                                                            {{ test.name }}
                                                       </td>
                                                       <td>
                                                            {{ test.price }}
                                                       </td>
                                                       <td>
                                                            {{ t("tests") }}
                                                       </td>
                                                       <td colspan="2"></td>
                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                            @click="removeTest(index, test.id)" />
                                                  </tr>
                                                  <tr
                                                       style="background: #3b82f62b"
                                                       v-show="showTests[index]"
                                                       v-for="culture in item.culture"
                                                       :key="`test-${culture.id}`">
                                                       <td>
                                                            {{ culture.name }}
                                                       </td>
                                                       <td>
                                                            {{ culture.price }}
                                                       </td>
                                                       <td>
                                                            {{ t("cultures") }}
                                                       </td>
                                                       <td colspan="2"></td>

                                                       <Button
                                                            icon="pi pi-trash"
                                                            class="p-button-rounded p-button-danger p-button-text p-button-sm"
                                                            @click="removeCulture(index, culture.id)" />
                                                  </tr>
                                             </template>
                                        </tbody>
                                   </table>
                              </div>
                         </TabPanel>
                    </TabView>
               </div>
               <br />
               <!-- inovice datails -->
               <div class="details">
                    <div class="add d-flex">
                         <h3>{{ t("inovice_datails") }}</h3>
                    </div>
                    <hr />
                    <div class="grid mt-1">
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("from_lab") }}</label>

                              <Dropdown
                                   style="text-align: center"
                                   showClear
                                   class="w-full"
                                   v-model="record.from_lab_id_fk"
                                   :options="fromLap"
                                   @change="price_list(record.from_lab_id_fk)"
                                   optionValue="id"
                                   optionLabel="name"
                                   :placeholder="t('select')" />
                         </div>
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("sample_collector") }}</label>

                              <Dropdown
                                   class="w-full"
                                   style="text-align: center"
                                   showClear
                                   v-model="record.sample_collector_id_fk"
                                   :options="collectorsList"
                                   @change="getTotal()"
                                   optionLabel="name"
                                   optionValue="id"
                                   :placeholder="t('select')" />
                         </div>
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("contract") }}</label>

                              <Dropdown
                                   class="w-full"
                                   style="text-align: center"
                                   showClear
                                   v-model="record.contract_id_fk"
                                   @change="cheackMaximunInvoice()"
                                   :options="contractsList"
                                   optionLabel="name"
                                   optionValue="id"
                                   :placeholder="t('select')" />
                              <Message severity="warn" v-if="ErrorMessage">{{ ErrorMessage }}</Message>
                         </div>

                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("referrals") }}</label>

                              <Dropdown
                                   class="w-full"
                                   style="text-align: center"
                                   showClear
                                   v-model="record.referral_id_fk"
                                   @change="getTotal()"
                                   :options="theDoctorReferal"
                                   optionLabel="name"
                                   optionValue="id"
                                   :placeholder="t('select')" />
                         </div>
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Registration_date") }}</label>
                              <InputText class="w-full" type="date" v-model="record.registration_date" />
                         </div>
                         <!-- <div class="col-3 pb-0">
                              <div class="flex">
                                   <div class="pb-0 checkBox">
                                        <Checkbox v-model="record.show_result_date" inputId="ingredient2" binary />
                                        <label class="block text-md mr-2">{{ t("show_result_date") }}</label>
                                   </div>
                                   <div>
                                        <label class="block text-md mb-2">{{ t("Result_date") }}</label>
                                   </div>
                              </div>

                              <InputText class="w-full" type="date" v-model="record.result_date" />
                         </div> -->
                    </div>
               </div>
               <br />
               <!-- payment  -->
               <div class="details">
                    <div class="add d-flex">
                         <h3 class="add_payment">
                              {{ t("paymentMethod") }}
                         </h3>
                         <!-- <span>{{ total }}</span> -->
                    </div>
                    <div class="mt-1">
                         <div>
                              <!-- Button to add a new row -->

                              <!-- Table for Payment Details -->

                              <table class="table" v-if="payment_details">
                                   <thead>
                                        <tr>
                                             <th>
                                                  {{ t("paymentMethod") }}
                                             </th>
                                             <!-- <th>{{ t("contract") }}</th> -->
                                             <th>{{ t("amount") }}</th>
                                             <th>
                                                  <Button
                                                       icon="pi pi-plus"
                                                       class="p-button-rounded p-button-success p-button-text"
                                                       @click="addRow()" />
                                             </th>
                                        </tr>
                                   </thead>

                                   <tbody>
                                        <tr v-for="(payment, index) in payment_details" :key="index">
                                             <td>
                                                  <Dropdown
                                                       style="text-align: center; width: 50%"
                                                       showClear
                                                       v-model="payment.payment_method_id_fk"
                                                       :options="paymentMethods"
                                                       optionLabel="name"
                                                       optionValue="id"
                                                       :placeholder="t('select')" />
                                             </td>
                                             <!-- <td>
                                                  <Dropdown
                                                       v-model="payment.contract_id_fk"
                                                       :options="contracts"
                                                       optionLabel="label"
                                                       optionValue="value"
                                                       :placeholder="t('select')" />
                                             </td> -->
                                             <td>
                                                  <InputNumber
                                                       class="w-full"
                                                       type="number"
                                                       v-model.number="payment.amount"
                                                       @input="checkpaid(index)" />
                                             </td>
                                             <td>
                                                  <Button
                                                       icon="pi pi-trash"
                                                       class="p-button-rounded p-button-danger p-button-text"
                                                       @click="removeRow(index)" />
                                             </td>
                                        </tr>
                                   </tbody>
                              </table>
                         </div>
                    </div>
               </div>
               <br />
               <!-- inovice summary -->
               <div class="details">
                    <div class="add d-flex">
                         <h3 class="">
                              {{ t("Invoice_summary") }}
                         </h3>
                    </div>
                    <div class="grid mt-1">
                         <!-- Total Input (Disabled) -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Total") }}</label>

                              <InputNumber class="w-full" type="number" v-model.number="record.total" disabled />
                         </div>

                         <!-- Subtotal Input (Disabled) -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Subtotal") }}</label>
                              <InputNumber class="w-full" type="number" v-model.number="record.sub_total" disabled />
                         </div>
                         <!-- Sample_collection_fees -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Sample_collection_fees") }}</label>
                              <InputNumber
                                   class="w-full"
                                   type="number"
                                   v-model.number="Sample_collection_fees"
                                   disabled />
                         </div>

                         <!-- paid -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("paid") }}</label>

                              <InputNumber class="w-full" type="number" v-model.number="the_paid" disabled />
                         </div>

                         <!-- Due Input (Disabled) -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Due") }}</label>
                              <InputNumber class="w-full" type="number" v-model.number="due" disabled />
                         </div>
                         <!-- Discount Inputs -->
                         <div class="col-4 pb-0">
                              <label class="block text-md mb-2">{{ t("it_discount") }}</label>
                              <div style="display: flex">
                                   <div class="prece">
                                        <InputNumber
                                             type="number"
                                             v-model.number="discountPercentage"
                                             @input="updateDiscountFromPercentage()" />
                                        <span class="span">%</span>
                                   </div>

                                   <InputNumber
                                        type="number"
                                        v-model.number="discountValue"
                                        @input="updateDiscountFromValue()" />
                              </div>
                         </div>
                         <!--Contract_payment -->
                         <div class="col-4 pb-0">
                              <label class="block text-md mb-2">{{ t("Contract_payment") }}</label>
                              <div style="display: flex">
                                   <div class="prece">
                                        <InputNumber
                                             type="number"
                                             v-model.number="Contract_discount_percentage"
                                             disabled />
                                        <span class="span">%</span>
                                   </div>

                                   <InputNumber type="number" v-model.number="payment_percent" disabled />
                              </div>
                         </div>
                         <!--Referral_commission -->
                         <div class="col-3 pb-0">
                              <label class="block text-md mb-2">{{ t("Referral_commission") }}</label>
                              <InputNumber type="number" v-model.number="commission" disabled />
                         </div>
                         <!-- <div class="col-md-6">
                              <label>Discount (Number)</label>
                              <input
                                   type="number"
                                   v-model.number="discountValue"
                                   @input="updateDiscountFromValue"
                                   placeholder="Enter value" />
                         </div> -->
                    </div>
               </div>
               <div class="flex justify-content-end gap-2 border-top-1 border-bluegray-100 mt-3 pt-3">
                    <Button size="small" :label="t('close')" severity="danger" @click="close()"></Button>
                    <Button
                         size="small"
                         type="submit"
                         :label="record.id ? t('save') : t('add')"
                         severity="success"></Button>
               </div>
          </form>
     </Dialog>
     <printInvoiceModal></printInvoiceModal>
</template>

<script>
     import { mapActions, mapWritableState, mapGetters } from "pinia";
     import { useinvoicesStore } from "@/store/modules/invoices";
     import { uselabsStore } from "@/store/modules/labs";
     import { LoaderStore } from "@/store/modules/loader";
     import { useContractsStore } from "@/store/modules/contracts";
     import { useReferralsStore } from "@/store/modules/referrals";
     import { useculturesStore } from "@/store/modules/cultures";
     import { usetestsStore } from "@/store/modules/tests";
     import { usePackagesStore } from "@/store/modules/packages";
     import { usepaymentMethodstore } from "@/store/modules/payment-methods";
     import { useresultStatusStore } from "@/store/modules/result-status";
     import { showAlertWithConfirm } from "@/utils/helper";
     import { usePatientsStore } from "@/store/modules/patients";
     import printInvoiceModal from "./printInvoice_modal.vue";
     import { $http } from "@/plugins/axios";
     import { usetestGroupsStore } from "@/store/modules/testGroups";
     import { useNationalitiesStore } from "@/store/modules/nationalities";
     import { useTitlesStore } from "@/store/modules/titles";
     export default {
          data() {
               return {
                    errorMessage: "",
                    selectedTest: null,
                    selectedCulture: null,
                    selectedPackage: null,
                    selectedtestGroup: null,
                    search_pationt: "",
                    isNameShow: false,
                    isCodeShow: false,
                    isphoneShow: false,
                    perceInput: false,
                    showTests: [],
                    valueInput: false,
                    paid: 0,
                    index: 0,
                    Contract_payment: 0,
                    paymentPercent: 0,
                    price_list_id: null,
                    showInlineMessage: true,
                    showAddPatientForm: false,
                    age_unitErrormessage: "",
                    genderErrormessage: "",
                    titleErrormessage: "",
                    isAddPatientNameShow: false,
                    selectedPatientFromSearch: null,
               };
          },
          components: {
               printInvoiceModal,
          },
          computed: {
               ...mapWritableState(LoaderStore, ["hideLoading"]),
               ...mapWritableState(useresultStatusStore, ["resultStatus"]),
               ...mapWritableState(useinvoicesStore, [
                    "record",
                    "dialog",
                    "searchNameTotalCount",
                    "searchCodeTotalCount",
                    "searchPhoneTotalCount",
                    "searchRecords",
                    "discountPercentage",
                    "discountValue",
                    "testQuesions",
                    "tests_ids",
                    "payment_details",
                    "selectedContract",
                    "selectedTests",
                    "selectedPackages",
                    "selectedtestGroups",
                    "selectedCultures",
                    "selectedreferal",
                    "selectedCollector",
                    "patient",
                    "printInvoiceDialog",
                    "printRecord",
               ]),
               ...mapWritableState(usetestGroupsStore, ["testGroups"]),
               ...mapWritableState(usePatientsStore, {
                    patientRecord: "record",
                    responseData: "responseData",
                    selectedFile: "selectedFile",
                    AgeUnits: "AgeUnits",
                    genders: "genders",
               }),
               ...mapWritableState(useNationalitiesStore, ["nationalities"]),
               ...mapWritableState(useTitlesStore, ["titles"]),
               ...mapWritableState(usetestsStore, ["tests", "pagination"]),
               ...mapGetters(uselabsStore, ["lab"]),
               ...mapWritableState(uselabsStore, ["collectorsList"]),
               ...mapWritableState(useReferralsStore, ["records"]),
               ...mapWritableState(useContractsStore, ["contracts", "contractsList"]),
               ...mapWritableState(useculturesStore, ["cultures"]),
               ...mapWritableState(usePackagesStore, ["packagesList"]),
               ...mapWritableState(usepaymentMethodstore, ["paymentMethods"]),
               Contract_discount_percentage() {
                    return (
                         this.contractsList?.find((c) => c.id === this.record.contract_id_fk)?.discount_percentage ?? 0
                    );
               },
               items() {
                         return this.tests;
                    },
               fromLap() {
                    return this.records.filter((item) => item.role === "Lab");
               },
               theDoctorReferal() {
                    return this.records.filter((item) => item.role === "Doctor");
               },
               payment_percent() {
                    return this.contractsList?.find((c) => c.id === this.record.contract_id_fk)?.payment_percent ?? 0;
               },
               commission() {
                    return this.records?.find((c) => c.id === this.record.referral_id_fk)?.commission ?? 0;
               },
               maximumInvoiceAmount() {
                    return (
                         this.contractsList?.find((c) => c.id === this.record.contract_id_fk)
                              ?.maximum_payment_per_invoice ?? 0
                    );
               },

               price_list_id_fk() {
                    return this.price_list_id;
               },
               Sample_collection_fees() {
                    return (
                         this.collectorsList?.find((c) => c.id === this.record.sample_collector_id_fk)?.commission ?? 0
                    );
               },
               // Calculate the total after applying the discount
               discountedTotal() {
                    const t =
                         this.baseTotal +
                         this.payment_percent +
                         this.commission +
                         this.Sample_collection_fees -
                         this.discountValue;
                    return t > 0 ? t : 0;
               },

               // Calculate the due amount based on the subtotal
               // due() {
               //      const paymentsTotal = this.paymentMethods.reduce((acc, payment) => acc + payment.amount, 0);
               //      return this.discountedTotal - paymentsTotal;
               // },
               baseTotal() {
                    const getPrice = (item) => {
                         if (!this.price_list_id_fk || !item.prices) {
                              return item.price || item.original_price || 0;
                         }

                         const priceListItem = item.prices.find(
                              (price) => price.price_list_id === this.price_list_id_fk
                         );

                         return priceListItem?.price_for_customer || item.price || item.original_price || 0;
                    };

                    const selectedTests = this.selectedTests?.map((item) => ({
                         ...item,
                         thePrice: getPrice(item),
                    }));

                    const selectedCultures = this.selectedCultures?.map((item) => ({
                         ...item,
                         thePrice: getPrice(item),
                    }));

                    const selectedPackages = this.selectedPackages?.map((item) => ({
                         ...item,
                         thePrice: getPrice(item),
                    }));

                    const selectedtestGroups = this.selectedtestGroups?.map((item) => {
                         // Calculate prices for tests within the group
                         const groupTestsTotal = item.tests?.reduce((acc, test) => acc + getPrice(test), 0) || 0;

                         // Calculate prices for cultures within the group
                         const groupCulturesTotal =
                              item.culture?.reduce((acc, culture) => acc + getPrice(culture), 0) || 0;

                         return {
                              ...item,
                              thePrice: getPrice(item) + groupTestsTotal + groupCulturesTotal,
                         };
                    });

                    const testsTotal = selectedTests?.reduce((acc, test) => acc + test.thePrice, 0) || 0;
                    const culturesTotal = selectedCultures?.reduce((acc, culture) => acc + culture.thePrice, 0) || 0;
                    const packagesTotal = selectedPackages?.reduce((acc, p) => acc + p.thePrice, 0) || 0;
                    const groupTotal = selectedtestGroups?.reduce((acc, p) => acc + p.thePrice, 0) || 0;

                    return testsTotal + culturesTotal + packagesTotal + groupTotal;
               },

               // Calculate the due amount based on the subtotal and discount value
               due() {
                    const d = this.record.total - this.the_paid;
                    return d > 0 ? d : 0;
               },

               the_paid() {
                    return this.payment_details.reduce((acc, payment) => acc + payment.amount, 0);
               },
               ErrorMessage() {
                    return this.errorMessage;
               },
          },
          created() {
               // Set today's date in YYYY-MM-DD format when the component is created
               const today = new Date();
               const year = today.getFullYear();
               const month = String(today.getMonth() + 1).padStart(2, "0"); // Add leading zero for months
               const day = String(today.getDate()).padStart(2, "0"); // Add leading zero for days
               this.record.registration_date = `${year}-${month}-${day}`;
          },
          mounted() {
               this.Getlabs();
               this.collectors();
               this.GetContracts();
               this.GetRecords();
               this.GetTests();
               this.Getcultures();
               this.Getpackages();
               this.GetpaymentMethods();
               this.GetresultStatus();
               this.GettestGroups();
               this.GetAgeUnits();
               this.GetGenders();
               this.GetTitles();
               this.GetNationalities();
          },
          methods: {
               ...mapActions(usetestGroupsStore, ["GettestGroups"]),
               ...mapActions(usepaymentMethodstore, ["GetpaymentMethods"]),
               ...mapActions(useresultStatusStore, ["GetresultStatus"]),
               ...mapActions(usetestsStore, ["GetTests"]),
               ...mapActions(useinvoicesStore, [
                    "Addinvoices",
                    "Updateinvoices",
                    "searchByname",
                    "searchBycode",
                    "searchByPhone",
                    "questions",
               ]),
               ...mapActions(uselabsStore, ["Getlabs", "collectors"]),
               ...mapActions(useReferralsStore, ["GetRecords"]),
               ...mapActions(useContractsStore, ["GetContracts"]),
               ...mapActions(usePackagesStore, ["Getpackages"]),
               ...mapActions(useculturesStore, ["Getcultures"]),
               ...mapActions(usePatientsStore, [
                    "AddPatient",
                    "GetAgeUnits",
                    "GetGenders",
                    "searchByname",
               ]),
               ...mapActions(useNationalitiesStore, ["GetNationalities"]),
               ...mapActions(useTitlesStore, ["GetTitles"]),
               cheackMaximunInvoice() {
                    this.getTotal();
                    if (this.record.total > this.maximumInvoiceAmount) {
                         this.errorMessage = this.t("MUximunInvoiceError");
                         this.record.contract_id_fk = null;
                    } else {
                         this.errorMessage = "";
                    }
               },
               toggleAddPatientForm() {
                    this.showAddPatientForm = !this.showAddPatientForm;
                    if (this.showAddPatientForm) {
                         this.clearObjectValues(this.patientRecord);
                         this.age_unitErrormessage = "";
                         this.genderErrormessage = "";
                         this.titleErrormessage = "";
                         this.isAddPatientNameShow = false;
                         this.selectedPatientFromSearch = null;
                    }
               },
               searchPatientByname(v) {
                    if (v) {
                         this.searchByname(v);
                         this.isAddPatientNameShow = true;
                    } else {
                         this.isAddPatientNameShow = false;
                    }
               },
               createNewPatient() {
                    if (this.patientRecord.age_unit_id_fk && this.patientRecord.gender_type_id_fk && this.patientRecord.title_id_fk) {
                         this.patientRecord.phone_number = this.patientRecord.phone;
                         this.patientRecord.gender_id_fk = this.patientRecord.gender_type_id_fk;
                         this.AddPatient().then(() => {
                              this.alertSuccess(this.t("alertSuccess"));
                              this.patient = this.responseData;
                              this.showAddPatientForm = false;
                              this.clearObjectValues(this.patientRecord);
                         });
                         this.age_unitErrormessage = "";
                         this.genderErrormessage = "";
                         this.titleErrormessage = "";
                    } else if (this.patientRecord.age_unit_id_fk == "" || this.patientRecord.age_unit_id_fk == null) {
                         this.age_unitErrormessage = this.t("errorMessage");
                    } else if (this.patientRecord.gender_type_id_fk == "" || this.patientRecord.gender_type_id_fk == null) {
                         this.genderErrormessage = this.t("errorMessage");
                    } else if (this.patientRecord.title_id_fk == "" || this.patientRecord.title_id_fk == null) {
                         this.titleErrormessage = this.t("errorMessage");
                    }
               },
               onFileChange(event) {
                    this.selectedFile = event.target.files[0];
               },
               changeTitle(value) {
                    if (value !== "" && value != null) {
                         this.genderErrormessage = "";
                    }
                    if (value == 1) {
                         this.patientRecord.title_id_fk = 1;
                    } else {
                         this.patientRecord.title_id_fk = 2;
                    }
               },
               ageChange(v) {
                    if (v !== "" && v != null) {
                         this.age_unitErrormessage = "";
                    }
               },
               titleChange(v) {
                    if (v !== "" && v != null) {
                         this.titleErrormessage = "";
                    }
               },
               async fetchTests(query) {
      try {
        const { data } = await $http.get('/tests', {
          params: {
            name: query
          }
        })
        this.tests = data.data // أو حسب شكل الداتا بالاستجابة
      } catch (error) {
        console.error('Error fetching tests:', error)
      }
    },
    onFilter(event) {
      const query = event.value

      // عمل ديبونس: انتظار بعد الكتابة شوية قبل ما نرسل ريكويست
      clearTimeout(this.searchTimeout)

      this.searchTimeout = setTimeout(() => {
        this.fetchTests(query)
      }, 300) // تأخير 300ms
    },
    addSelection(type, value) {
      console.log(type, value)
      // هنا تحط الكود حسب شغلك
    },
    onScroll(event) {
      console.log('Scrolled!', event)
      // تقدر تضيف لود مور مثلا هنا
    },
               toggletests(index) {
                    // Toggle the visibility of attributes table for the item at the given index
                    this.showTests[index] = !this.showTests[index];
               },

               toggleCultures(index) {
                    this.showCultures[index] = !this.showCultures[index];
               },
               removeTest(packageIndex, testId) {
                    const packageItem = this.selectedPackages[packageIndex];
                    packageItem.tests = packageItem.tests.filter((test) => test.id !== testId);
               },
               removeCulture(packageIndex, cultureId) {
                    const packageItem = this.selectedPackages[packageIndex];
                    packageItem.cultures = packageItem.cultures.filter((culture) => culture.id !== cultureId);
               },
               checkpaid(index) {
                    this.index = index;
                    // if (v > this.due) {
                    //      alert("the paid must be less than the due ");
                    //      this.removeRow(index);
                    // }
               },
               price_list(v) {
                    this.price_list_id = this.fromLap?.find((c) => c.id === v)?.price_list_id_fk ?? null;
               },
               addRecord() {
                    this.clearObjectValues(this.patientRecord);
                    this.thepatientDialog = true;
               },
               updateDiscountFromPercentage() {
                    this.perceInput = true;
                    this.valueInput = false;
               },
               updateDiscountFromValue() {
                    this.valueInput = true;
                    this.perceInput = false;
               },
               getTotal() {
                    this.record.total =
                         this.baseTotal +
                         this.payment_percent +
                         this.commission +
                         this.Sample_collection_fees -
                         this.discountValue;
               },
               show_Questions() {
                    this.tests_ids = this.selectedTests.map((test) => test.id ?? test.test_id_fk);
                    if (this.tests_ids.length > 0) {
                         this.questions();
                    }
               },
               addSelection(type, item) {
                    if (item) {
                         const selectedItem = {
                              ...item,
                              thePrice: this.price_list_id_fk
                                   ? item.prices?.find((price) => price.price_list_id === this.price_list_id_fk)
                                          ?.price_for_customer
                                   : item.price,
                         };
                         if (type === "test") {
                              const exists = this.selectedTests.find((test) => test.id === item.id);
                              if (!exists) {
                                   this.selectedTests.push(selectedItem);
                              }
                         } else if (type === "package") {
                              const exists = this.selectedPackages.find((pkg) => pkg.id === item.id);
                              if (!exists) {
                                   this.selectedPackages.push(selectedItem);
                              }
                         } else if (type === "culture") {
                              const exists = this.selectedCultures.find((pkg) => pkg.id === item.id);
                              if (!exists) {
                                   this.selectedCultures.push(selectedItem);
                              }
                         } else if (type === "testGroup") {
                              const exists = this.selectedtestGroups.find((pkg) => pkg.id === item.id);
                              if (!exists) {
                                   this.selectedtestGroups.push(selectedItem);
                              }
                         }
                    }
               },
               getComponentType(type) {
                    switch (type) {
                         case "number":
                              return "input";
                         case "text":
                              return "input";
                         case "checkbox":
                              return "checkbox";
                         case "selections":
                              return "Dropdown";
                         default:
                              return "input";
                    }
               },
               handleQuestionChange(item) {
                    if (item.selectedQuestion) {
                         item.selectedQuestion.answer = null; // Reset answer when a new question is selected
                    }
               },
               removeSelection(type, index) {
                    if (type === "test") {
                         this.selectedTests.splice(index, 1);
                    } else if (type === "package") {
                         this.selectedPackages.splice(index, 1);
                    } else if (type === "culture") {
                         this.selectedCultures.splice(index, 1);
                    } else if (type === "testGroup") {
                         this.selectedtestGroups.splice(index, 1);
                    }
               },
               searchitemByname(v) {
                    if (v) {
                         this.searchByname(v);
                         this.isNameShow = true;
                         this.showInlineMessage = true;
                         setTimeout(() => {
                              this.showInlineMessage = false;
                         }, 3000); // Hide after 3 seconds
                    } else {
                         this.isNameShow = false;
                         // this.clearObjectValues(this.record);
                    }
               },
               searchitemBycode(v) {
                    if (v) {
                         this.searchBycode(v);
                         this.isCodeShow = true;
                    } else {
                         this.isCodeShow = false;
                         // this.clearObjectValues(this.record);
                    }
               },
               searchitemByphone(v) {
                    if (v) {
                         this.searchByPhone(v);
                         this.isphoneShow = true;
                    } else {
                         this.isphoneShow = false;
                         // this.clearObjectValues(this.record);
                    }
               },

               create() {
                    this.record.patient_id_fk = this.patient?.id ?? null;
                    this.record.show_patient_card_id = this.record?.show_patient_card_id ? true : false;
                    this.record.show_result_date = this.record?.show_result_date ? true : false;
                    this.record.show_patient_pic = this.record?.show_patient_pic ? true : false;
                    this.record.payment_details = this.payment_details;
                    if (this.perceInput) {
                         this.record.discount = this.discountPercentage;
                         this.record.discount_type_id_fk = 2;
                    } else if (this.valueInput) {
                         this.record.discount = this.discountValue;
                         this.record.discount_type_id_fk = 3;
                    } else {
                         this.record.discount = null;
                         this.record.discount_type_id_fk = null;
                    }
                    const getPrice = (item) => {
                         if (!this.price_list_id_fk || !item.prices) {
                              return item.price || item.original_price || 0;
                         }

                         const priceListItem = item.prices.find(
                              (price) => price.price_list_id === this.price_list_id_fk
                         );

                         return priceListItem?.price_for_customer || item.price || item.original_price || 0;
                    };

                    // Update tests prices
                    this.record.tests = this.selectedTests?.map((test) => ({
                         test_id_fk: test.id,
                         price: getPrice(test),
                         result: test.result ?? null,
                         comment: test.comment ?? null,
                         result_status_id_fk: test.result_status_id_fk ?? null,
                         // to_lab_id_fk: test.lab ?? null, //nullable,
                         to_lab_id_fk:  null, //nullable,

                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: this.testQuesions
                              ?.filter((v) => v.test_id_fk === test.id)
                              .flatMap((item) =>
                                   item.questions?.map((question) => ({
                                        question: {
                                             id: question.id,
                                             question: question.question,
                                             answer_type: question.answer_type,
                                             answer_type_id_fk: question.answer_type_id_fk,
                                             answer_type_selection_values: question.answer_type_selection_values,
                                        },
                                        answer:
                                             question.answer_type == "checkbox"
                                                  ? question.answer
                                                       ? true
                                                       : false
                                                  : question.answer,
                                   }))
                              ),
                    }));

                    // Update cultures prices
                    this.record.cultures = this.selectedCultures.map((culture) => ({
                         culture_id_fk: culture.id,
                         price: getPrice(culture),
                         result: culture.result ?? null,
                         comment: culture.comment ?? null,
                         result_status_id_fk: culture.result_status_id_fk ?? null, // required
                         to_lab_id_fk: culture.lab ?? null, //nullable,

                         is_sample_received: culture.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));

                    // Update packages prices
                    this.record.packages = this.selectedPackages.map((pkg) => ({
                         package_id_fk: pkg.id,
                         price: getPrice(pkg),
                         result: pkg.result ?? null,
                         package_tests: pkg.tests ?? [],
                         package_cultures: pkg.cultures ?? [],
                         comment: pkg.comment ?? null,
                         result_status_id_fk: pkg.result_status_id_fk ?? null, // required
                         to_lab_id_fk: pkg.to_lab ?? null, //nullable,

                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));

                    // Update test groups prices
                    this.record.test_groups = this.selectedtestGroups.map((group) => ({
                         test_group_id_fk: group.id,
                         price: getPrice(group),
                         result: group.result ?? null,
                         test_group_tests: group.tests ?? [],
                         test_group_cultures: group.culture ?? [],
                         comment: group.comment ?? null,
                         result_status_id_fk: group.result_status_id_fk ?? null, // required
                         to_lab_id_fk: group.to_lab ?? null, //nullable,

                         is_sample_received: group.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));
                    this.Addinvoices().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                    });
               },
               addRow() {
                    // Add a new row with default values
                    this.payment_details.push({
                         amount: null,
                         contract_id_fk: null,
                         payment_method_id_fk: null,
                    });
               },
               removeRow(index) {
                    // Remove the row at the specified index
                    this.payment_details.splice(index, 1);
               },
               update() {
                    this.record.patient_id_fk = this.patient?.id ?? null;
                    this.record.show_patient_card_id = this.record?.show_patient_card_id ? true : false;
                    this.record.show_result_date = this.record?.show_result_date ? true : false;
                    this.record.show_patient_pic = this.record?.show_patient_pic ? true : false;
                    this.record.payment_details = this.payment_details;
                    if (this.perceInput) {
                         this.record.discount = this.discountPercentage;
                         this.record.discount_type_id_fk = 2;
                    } else if (this.valueInput) {
                         this.record.discount = this.discountValue;
                         this.record.discount_type_id_fk = 3;
                    } else {
                         this.record.discount = null;
                         this.record.discount_type_id_fk = null;
                    }
                    this.record.tests = this.selectedTests?.map((test) => ({
                         test_id_fk: test.id ?? test.test_id_fk, // required
                         result: test.result ?? null,
                         comment: test.comment ?? null,
                         result_status_id_fk: test.result_status_id_fk ?? null,
                         to_lab_id_fk:  null, //nullable,
                         price: test.price ?? null,
                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: this.testQuesions
                              ?.filter((v) => v.test_id_fk === test.id)
                              .flatMap((item) =>
                                   item.questions?.map((question) => ({
                                        question: {
                                             id: question.id,
                                             question: question.question,
                                             answer_type: question.answer_type,
                                             answer_type_id_fk: question.answer_type_id_fk,
                                             answer_type_selection_values: question.answer_type_selection_values,
                                        },
                                        answer:
                                             question.answer_type == "checkbox"
                                                  ? question.answer
                                                       ? true
                                                       : false
                                                  : question.answer,
                                   }))
                              ),
                    }));
                    this.record.cultures = this.selectedCultures.map((test) => ({
                         culture_id_fk: test.id ?? test.culture_id_fk,
                         result: test.result ?? null,
                         comment: test.comment ?? null,
                         result_status_id_fk: test.result_status_id_fk ?? null, // required
                         to_lab_id_fk: test.lab ?? null, //nullable,
                         price: test.price ?? null,
                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));
                    this.record.packages = this.selectedPackages.map((test) => ({
                         package_id_fk: test.id,
                         result: test.result ?? null,
                         package_tests: test.tests ?? [],
                         package_cultures: test.cultures ?? [],
                         comment: test.comment ?? null,
                         result_status_id_fk: test.result_status_id_fk ?? null, // required
                         to_lab_id_fk: test.to_lab ?? null, //nullable,
                         price: this.price_list_id_fk
                              ? test.prices?.find((price) => price.price_list_id == price_list_id_fk)
                                     ?.price_for_customer
                                   ? test.prices?.find((price) => price.price_list_id == price_list_id_fk)
                                          ?.price_for_customer
                                   : test?.price
                              : test?.price,
                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));
                    this.record.test_groups = this.selectedtestGroups.map((test) => ({
                         test_group_id_fk: test.id,
                         result: test.result ?? null,
                         test_group_tests: test.tests ?? [],
                         test_group_cultures: test.culture ?? [],
                         comment: test.comment ?? null,
                         result_status_id_fk: test.result_status_id_fk ?? null, // required
                         to_lab_id_fk: test.to_lab ?? null, //nullable,
                         price: this.price_list_id_fk
                              ? test.prices?.find((price) => price.price_list_id == price_list_id_fk)
                                     ?.price_for_customer
                                   ? test.prices?.find((price) => price.price_list_id == price_list_id_fk)
                                          ?.price_for_customer
                                   : test?.price
                              : test?.price,
                         is_sample_received: test.is_sample_received ?? false, //bool if null will defualt to false
                         questions: null,
                    }));

                    this.Updateinvoices().then(() => {
                         this.dialog = false;
                         this.alertSuccess(this.t("alertSuccess"));
                         this.clearObjectValues(this.record);
                    });
               },
               close() {
                    this.selectedContract = [];
                    this.selectedreferal = [];
                    this.selectedTests = [];
                    this.selectedPackages = [];
                    this.selectedCultures = [];
                    this.payment_details = [
                         {
                              amount: null, // $request->paid,
                              contract_id_fk: null, //
                              payment_method_id_fk: null, // $request->payment_method_id_fk
                         },
                    ];
                    this.patient = [];
                    this.discountPercentage = 0;
                    this.discountValue = 0;
                    this.errorMessage = "";
                    this.clearObjectValues(this.record);
                    this.dialog = false;
               },

               onScroll(event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop === event.target.clientHeight;
                    if (bottom && this.pagination.current_page < this.pagination.last_page) {
                         this.pagination.current_page++;
                         this.GetTests();
                    }
               },
          },
          watch: {
               baseTotal: function (v) {
                    this.record.sub_total = v;
                    this.record.total = this.baseTotal - this.discountValue;
                    this.record.total = this.record.total > 0 ? this.record.total : 0;
               },
               "record.total": function (v) {
                    this.record.total = this.discountedTotal > 0 ? this.discountedTotal : 0;
               },
               discountPercentage: function (v) {
                    // Update discountValue when discountPercentage is changed
                    if (this.discountPercentage !== null && this.discountPercentage >= 0) {
                         this.discountValue = (this.baseTotal * this.discountPercentage) / 100;
                         this.record.total = this.baseTotal - this.discountValue;
                         this.record.total = this.record.total > 0 ? this.record.total : 0;
                    } else {
                         this.discountValue = 0;
                    }
               },
               discountValue: function (v) {
                    // Update discountValue when discountPercentage is changed
                    if (this.discountValue !== null && this.discountValue >= 0) {
                         this.discountPercentage = (this.discountValue / this.baseTotal) * 100;
                    } else {
                         this.discountPercentage = 0;
                    }
               },

               patient: function (v) {
                    if (v) {
                         this.isNameShow = false;
                         this.isCodeShow = false;
                         this.isphoneShow = false;
                    }
               },
               "record.from_lab_id_fk": function (v) {
                    this.price_list_id = this.fromLap?.find((c) => c.id === v)?.price_list_id_fk ?? null;
               },
               selectedPatientFromSearch: function (v) {
                    if (v) {
                         this.isAddPatientNameShow = false;
                         this.patientRecord = { ...v };
                    }
               },
          },
     };
</script>
<style scoped lang="scss">
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
     .add-patient-form {
          background: #f9fafb;
          padding: 20px;
          border-radius: 10px;
          border: 1px solid #d1d5db;
     }
     .text-right {
          text-align: right;
     }
     .save-patient-btn .p-button-icon {
          margin-left: 10px;
     }
</style>
