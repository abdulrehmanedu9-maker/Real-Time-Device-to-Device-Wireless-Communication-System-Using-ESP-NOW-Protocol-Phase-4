#pragma once
#include <cstdarg>
namespace Eloquent {
    namespace ML {
        namespace Port {
            class DecisionTree {
                public:
                    /**
                    * Predict class for features vector
                    */
                    int predict(float *x) {
                        if (x[0] <= 13.244999885559082) {
                            if (x[0] <= 6.605000019073486) {
                                if (x[0] <= 5.045000076293945) {
                                    if (x[0] <= 3.9350000619888306) {
                                        if (x[0] <= 3.0999999046325684) {
                                            return 2;
                                        }

                                        else {
                                            return 2;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 4.7149999141693115) {
                                            return 2;
                                        }

                                        else {
                                            return 2;
                                        }
                                    }
                                }

                                else {
                                    if (x[0] <= 5.825000047683716) {
                                        if (x[1] <= 1.0950000286102295) {
                                            return 2;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 5.954999923706055) {
                                            return 1;
                                        }

                                        else {
                                            return 2;
                                        }
                                    }
                                }
                            }

                            else {
                                if (x[0] <= 12.224999904632568) {
                                    if (x[0] <= 7.585000038146973) {
                                        if (x[0] <= 7.015000104904175) {
                                            return 1;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 11.555000305175781) {
                                            return 1;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }
                                }

                                else {
                                    if (x[0] <= 12.555000305175781) {
                                        if (x[1] <= 1.0850000381469727) {
                                            return 1;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 13.234999656677246) {
                                            return 1;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }
                                }
                            }
                        }

                        else {
                            if (x[0] <= 14.845000267028809) {
                                if (x[0] <= 13.934999942779541) {
                                    if (x[0] <= 13.585000038146973) {
                                        if (x[1] <= 0.9950000047683716) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 13.605000019073486) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }
                                }

                                else {
                                    if (x[0] <= 14.46500015258789) {
                                        if (x[1] <= 2.069999933242798) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }

                                    else {
                                        if (x[1] <= 2.284999966621399) {
                                            return 0;
                                        }

                                        else {
                                            return 1;
                                        }
                                    }
                                }
                            }

                            else {
                                if (x[0] <= 16.0649995803833) {
                                    if (x[0] <= 15.28499984741211) {
                                        if (x[1] <= 1.8949999809265137) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 16.054999351501465) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }
                                }

                                else {
                                    if (x[0] <= 17.484999656677246) {
                                        if (x[0] <= 16.515000343322754) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }

                                    else {
                                        if (x[0] <= 18.015000343322754) {
                                            return 0;
                                        }

                                        else {
                                            return 0;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    /**
                    * Predict readable class name
                    */
                    const char* predictLabel(float *x) {
                        return idxToLabel(predict(x));
                    }

                    /**
                    * Convert class idx to readable name
                    */
                    const char* idxToLabel(uint8_t classIdx) {
                        switch (classIdx) {
                            case 0:
                            return "normal";
                            case 1:
                            return "warning";
                            case 2:
                            return "critical";
                            default:
                            return "Houston we have a problem";
                        }
                    }

                protected:
                };
            }
        }
    }